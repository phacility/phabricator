<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorAuditCommentEditor {

  private $commit;
  private $user;

  private $attachInlineComments;

  public function __construct(PhabricatorRepositoryCommit $commit) {
    $this->commit = $commit;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setAttachInlineComments($attach_inline_comments) {
    $this->attachInlineComments = $attach_inline_comments;
    return $this;
  }

  public function addComment(PhabricatorAuditComment $comment) {

    $commit = $this->commit;
    $user = $this->user;

    $other_comments = id(new PhabricatorAuditComment())->loadAllWhere(
      'targetPHID = %s',
      $commit->getPHID());

    $inline_comments = array();
    if ($this->attachInlineComments) {
      $inline_comments = id(new PhabricatorAuditInlineComment())->loadAllWhere(
        'authorPHID = %s AND commitPHID = %s
          AND auditCommentID IS NULL',
        $user->getPHID(),
        $commit->getPHID());
    }

    $comment
      ->setActorPHID($user->getPHID())
      ->setTargetPHID($commit->getPHID())
      ->save();

    if ($inline_comments) {
      foreach ($inline_comments as $inline) {
        $inline->setAuditCommentID($comment->getID());
        $inline->save();
      }
    }

    // When a user submits an audit comment, we update all the audit requests
    // they have authority over to reflect the most recent status. The general
    // idea here is that if audit has triggered for, e.g., several packages, but
    // a user owns all of them, they can clear the audit requirement in one go
    // without auditing the commit for each trigger.

    $audit_phids = self::loadAuditPHIDsForUser($this->user);
    $audit_phids = array_fill_keys($audit_phids, true);

    $requests = id(new PhabricatorRepositoryAuditRequest())
      ->loadAllWhere(
        'commitPHID = %s',
        $commit->getPHID());

    $action = $comment->getAction();
    $status_map = PhabricatorAuditActionConstants::getStatusNameMap();
    $status = idx($status_map, $action, null);

    // Status may be empty for updates which don't affect status, like
    // "comment".
    $have_any_requests = false;
    foreach ($requests as $request) {
      if (empty($audit_phids[$request->getAuditorPHID()])) {
        continue;
      }
      $have_any_requests = true;
      if ($status) {
        $request->setAuditStatus($status);
        $request->save();
      }
    }

    if (!$have_any_requests) {
      // If the user has no current authority over any audit trigger, make a
      // new one to represent their audit state.
      $request = id(new PhabricatorRepositoryAuditRequest())
        ->setCommitPHID($commit->getPHID())
        ->setAuditorPHID($user->getPHID())
        ->setAuditStatus(
            $status
              ? $status
              : PhabricatorAuditStatusConstants::AUDIT_NOT_REQUIRED)
        ->setAuditReasons(array("Voluntary Participant"))
        ->save();
      $requests[] = $request;
    }

    $commit->updateAuditStatus($requests);
    $commit->save();

    $this->publishFeedStory($comment, array_keys($audit_phids));
    PhabricatorSearchCommitIndexer::indexCommit($commit);
    $this->sendMail($comment, $other_comments);
  }


  /**
   * Load the PHIDs for all objects the user has the authority to act as an
   * audit for. This includes themselves, and any packages they are an owner
   * of.
   */
  public static function loadAuditPHIDsForUser(PhabricatorUser $user) {
    $phids = array();

    // The user can audit on their own behalf.
    $phids[$user->getPHID()] = true;

    // The user can audit on behalf of all packages they own.
    $owned_packages = id(new PhabricatorOwnersOwner())->loadAllWhere(
      'userPHID = %s',
      $user->getPHID());
    if ($owned_packages) {
      $packages = id(new PhabricatorOwnersPackage())->loadAllWhere(
        'id IN (%Ld)',
        mpull($owned_packages, 'getPackageID'));
      foreach (mpull($packages, 'getPHID') as $phid) {
        $phids[$phid] = true;
      }
    }

    // The user can audit on behalf of all projects they are a member of.
    $query = new PhabricatorProjectQuery();
    $query->setMembers(array($user->getPHID()));
    $projects = $query->execute();
    foreach ($projects as $project) {
      $phids[$project->getPHID()] = true;
    }

    return array_keys($phids);
  }

  private function publishFeedStory(
    PhabricatorAuditComment $comment,
    array $more_phids) {

    $commit = $this->commit;
    $user = $this->user;

    $related_phids = array_merge(
      array(
        $user->getPHID(),
        $commit->getPHID(),
      ),
      $more_phids);

    id(new PhabricatorFeedStoryPublisher())
      ->setRelatedPHIDs($related_phids)
      ->setStoryAuthorPHID($user->getPHID())
      ->setStoryTime(time())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_AUDIT)
      ->setStoryData(
        array(
          'commitPHID'    => $commit->getPHID(),
          'action'        => $comment->getAction(),
          'content'       => $comment->getContent(),
        ))
      ->publish();
  }

  private function sendMail(
    PhabricatorAuditComment $comment,
    array $other_comments) {
    $commit = $this->commit;

    $data = $commit->loadCommitData();
    $summary = $data->getSummary();

    $commit_phid = $commit->getPHID();
    $phids = array($commit_phid);
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $handle = $handles[$commit_phid];

    $name = $handle->getName();

    $map = array(
      PhabricatorAuditActionConstants::CONCERN  => 'Raised Concern',
      PhabricatorAuditActionConstants::ACCEPT   => 'Accepted',
    );
    $verb = idx($map, $comment->getAction(), 'Commented On');

    $reply_handler = self::newReplyHandlerForCommit($commit);

    $prefix = PhabricatorEnv::getEnvConfig('metamta.diffusion.subject-prefix');
    $subject = "{$prefix} [{$verb}] {$name}: {$summary}";

    $threading = self::getMailThreading($commit->getPHID());
    list($thread_id, $thread_topic) = $threading;

    $is_new     = !count($other_comments);
    $body       = $this->renderMailBody(
      $comment,
      "{$name}: {$summary}",
      $handle,
      $reply_handler);

    $email_to = array();

    $author_phid = $data->getCommitDetail('authorPHID');
    if ($author_phid) {
      $email_to[] = $author_phid;
    }

    $email_cc = array();
    foreach ($other_comments as $comment) {
      $email_cc[] = $comment->getActorPHID();
    }

    $phids = array_merge($email_to, $email_cc);
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $template = id(new PhabricatorMetaMTAMail())
      ->setSubject($subject)
      ->setFrom($comment->getActorPHID())
      ->setThreadID($thread_id, $is_new)
      ->addHeader('Thread-Topic', $thread_topic)
      ->setRelatedPHID($commit->getPHID())
      ->setIsBulk(true)
      ->setBody($body);

    $mails = $reply_handler->multiplexMail(
      $template,
      array_select_keys($handles, $email_to),
      array_select_keys($handles, $email_cc));

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }
  }

  public static function getMailThreading($phid) {
    return array(
      '<diffusion-audit-'.$phid.'>',
      'Diffusion Audit '.$phid,
    );
  }

  public static function newReplyHandlerForCommit($commit) {
    $handler_class = PhabricatorEnv::getEnvConfig(
      'metamta.diffusion.reply-handler');
    $reply_handler = newv($handler_class, array());
    $reply_handler->setMailReceiver($commit);
    return $reply_handler;
  }

  private function renderMailBody(
    PhabricatorAuditComment $comment,
    $cname,
    PhabricatorObjectHandle $handle,
    PhabricatorMailReplyHandler $reply_handler) {

    $commit = $this->commit;
    $user = $this->user;
    $name = $user->getUsername();

    $verb = PhabricatorAuditActionConstants::getActionPastTenseVerb(
      $comment->getAction());

    $body = array();
    $body[] = "{$name} {$verb} commit {$cname}.";

    if ($comment->getContent()) {
      $body[] = $comment->getContent();
    }

    $body[] = "COMMIT\n  ".PhabricatorEnv::getProductionURI($handle->getURI());

    $reply_instructions = $reply_handler->getReplyHandlerInstructions();
    if ($reply_instructions) {
      $body[] = "REPLY HANDLER ACTIONS\n  ".$reply_instructions;
    }

    return implode("\n\n", $body)."\n";
  }

}
