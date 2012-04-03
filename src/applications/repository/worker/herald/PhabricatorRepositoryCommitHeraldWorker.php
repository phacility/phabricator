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

final class PhabricatorRepositoryCommitHeraldWorker
  extends PhabricatorRepositoryCommitParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());

    if (!$data) {
      // TODO: Permanent failure.
      return;
    }

    $rules = HeraldRule::loadAllByContentTypeWithFullData(
      HeraldContentTypeConfig::CONTENT_TYPE_COMMIT,
      $commit->getPHID());

    $adapter = new HeraldCommitAdapter(
      $repository,
      $commit,
      $data);
    $engine = new HeraldEngine();

    $effects = $engine->applyRules($rules, $adapter);
    $engine->applyEffects($effects, $adapter, $rules);

    $audit_phids = $adapter->getAuditMap();
    if ($audit_phids) {
      $this->createAudits($commit, $audit_phids, $rules);
    }

    $this->createAuditsFromCommitMessage($commit, $data);

    $email_phids = $adapter->getEmailPHIDs();
    if (!$email_phids) {
      return;
    }

    if ($repository->getDetail('herald-disabled')) {
      // This just means "disable email"; audits are (mostly) idempotent.
      return;
    }

    $xscript = $engine->getTranscript();

    $revision = $adapter->loadDifferentialRevision();
    if ($revision) {
      $name = $revision->getTitle();
    } else {
      $name = $data->getSummary();
    }

    $author_phid = $data->getCommitDetail('authorPHID');
    $reviewer_phid = $data->getCommitDetail('reviewerPHID');

    $phids = array_filter(
      array(
        $author_phid,
        $reviewer_phid,
        $commit->getPHID(),
      ));

    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $commit_handle = $handles[$commit->getPHID()];
    $commit_name = $commit_handle->getName();

    if ($author_phid) {
      $author_name = $handles[$author_phid]->getName();
    } else {
      $author_name = $data->getAuthorName();
    }

    if ($reviewer_phid) {
      $reviewer_name = $handles[$reviewer_phid]->getName();
    } else {
      $reviewer_name = null;
    }

    $who = implode(', ', array_filter(array($author_name, $reviewer_name)));

    $description = $data->getCommitMessage();

    $commit_uri = PhabricatorEnv::getProductionURI($commit_handle->getURI());
    $differential = $revision
      ? PhabricatorEnv::getProductionURI('/D'.$revision->getID())
      : 'No revision.';

    $files = $adapter->loadAffectedPaths();
    sort($files);
    $files = implode("\n  ", $files);

    $xscript_id = $xscript->getID();

    $manage_uri = PhabricatorEnv::getProductionURI('/herald/view/commits/');
    $why_uri = PhabricatorEnv::getProductionURI(
      '/herald/transcript/'.$xscript_id.'/');

    $reply_handler = PhabricatorAuditCommentEditor::newReplyHandlerForCommit(
      $commit);

    $reply_instructions = $reply_handler->getReplyHandlerInstructions();
    if ($reply_instructions) {
      $reply_instructions =
        "\n".
        "REPLY HANDLER ACTIONS\n".
        "  ".$reply_instructions."\n";
    }


    $body = <<<EOBODY
DESCRIPTION
{$description}

DETAILS
  {$commit_uri}

DIFFERENTIAL REVISION
  {$differential}

AFFECTED FILES
  {$files}
{$reply_instructions}
MANAGE HERALD COMMIT RULES
  {$manage_uri}

WHY DID I GET THIS EMAIL?
  {$why_uri}

EOBODY;

    $subject = "[Herald/Commit] {$commit_name} ({$who}) {$name}";

    $threading = PhabricatorAuditCommentEditor::getMailThreading(
      $commit->getPHID());
    list($thread_id, $thread_topic) = $threading;

    $template = new PhabricatorMetaMTAMail();
    $template->setRelatedPHID($commit->getPHID());
    $template->setSubject($subject);
    $template->setBody($body);
    $template->setThreadID($thread_id, $is_new = true);
    $template->addHeader('Thread-Topic', $thread_topic);
    $template->setIsBulk(true);

    $template->addHeader('X-Herald-Rules', $xscript->getXHeraldRulesHeader());
    if ($author_phid) {
      $template->setFrom($author_phid);
    }

    $mails = $reply_handler->multiplexMail(
      $template,
      id(new PhabricatorObjectHandleData($email_phids))->loadHandles(),
      array());

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }
  }

  private function createAudits(
    PhabricatorRepositoryCommit $commit,
    array $map,
    array $rules) {
    assert_instances_of($rules, 'HeraldRule');

    $requests = id(new PhabricatorRepositoryAuditRequest())->loadAllWhere(
      'commitPHID = %s',
      $commit->getPHID());
    $requests = mpull($requests, null, 'getAuditorPHID');

    $rules = mpull($rules, null, 'getID');
    foreach ($map as $phid => $rule_ids) {
      $request = idx($requests, $phid);
      if ($request) {
        continue;
      }
      $reasons = array();
      foreach ($rule_ids as $id) {
        $rule_name = '?';
        if ($rules[$id]) {
          $rule_name = $rules[$id]->getName();
        }
        $reasons[] = 'Herald Rule #'.$id.' "'.$rule_name.'" Triggered Audit';
      }

      $request = new PhabricatorRepositoryAuditRequest();
      $request->setCommitPHID($commit->getPHID());
      $request->setAuditorPHID($phid);
      $request->setAuditStatus(PhabricatorAuditStatusConstants::AUDIT_REQUIRED);
      $request->setAuditReasons($reasons);
      $request->save();
    }

    $commit->updateAuditStatus($requests);
    $commit->save();
  }


  /**
   * Find audit requests in the "Auditors" field if it is present and trigger
   * explicit audit requests.
   */
  private function createAuditsFromCommitMessage(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $message = $data->getCommitMessage();

    $matches = null;
    if (!preg_match('/^Auditors:\s*(.*)$/im', $message, $matches)) {
      return;
    }

    $phids = DifferentialFieldSpecification::parseCommitMessageObjectList(
      $matches[1],
      $include_mailables = false,
      $allow_partial = true);

    if (!$phids) {
      return;
    }

    $requests = id(new PhabricatorRepositoryAuditRequest())->loadAllWhere(
      'commitPHID = %s',
      $commit->getPHID());
    $requests = mpull($requests, null, 'getAuditorPHID');

    foreach ($phids as $phid) {
      if (isset($requests[$phid])) {
        continue;
      }

      $request = new PhabricatorRepositoryAuditRequest();
      $request->setCommitPHID($commit->getPHID());
      $request->setAuditorPHID($phid);
      $request->setAuditStatus(
        PhabricatorAuditStatusConstants::AUDIT_REQUESTED);
      $request->setAuditReasons(
        array(
          'Requested by Author',
        ));
      $request->save();

      $requests[$phid] = $request;
    }

    $commit->updateAuditStatus($requests);
    $commit->save();
  }

}
