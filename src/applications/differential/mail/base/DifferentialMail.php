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

abstract class DifferentialMail {

  protected $to = array();
  protected $cc = array();

  protected $actorHandle;

  protected $revision;
  protected $comment;
  protected $changesets;
  protected $inlineComments;
  protected $isFirstMailAboutRevision;
  protected $isFirstMailToRecipients;
  protected $heraldTranscriptURI;
  protected $heraldRulesHeader;
  protected $replyHandler;
  protected $parentMessageID;

  protected function renderSubject() {
    $revision = $this->getRevision();
    $title = $revision->getTitle();
    $id = $revision->getID();
    return "D{$id}: {$title}";
  }

  abstract protected function renderVarySubject();
  abstract protected function renderBody();

  public function setActorHandle($actor_handle) {
    $this->actorHandle = $actor_handle;
    return $this;
  }

  public function getActorHandle() {
    return $this->actorHandle;
  }

  protected function getActorName() {
    $handle = $this->getActorHandle();
    if ($handle) {
      return $handle->getName();
    }
    return '???';
  }

  public function setParentMessageID($parent_message_id) {
    $this->parentMessageID = $parent_message_id;
    return $this;
  }

  public function setXHeraldRulesHeader($header) {
    $this->heraldRulesHeader = $header;
    return $this;
  }

  public function send() {
    $to_phids = $this->getToPHIDs();
    if (!$to_phids) {
      throw new Exception('No "To:" users provided!');
    }

    $cc_phids     = $this->getCCPHIDs();
    $subject      = $this->buildSubject();
    $vary_subject = $this->buildVarySubject();
    $body         = $this->buildBody();
    $attachments  = $this->buildAttachments();

    $template = new PhabricatorMetaMTAMail();
    $actor_handle = $this->getActorHandle();
    $reply_handler = $this->getReplyHandler();

    if ($actor_handle) {
      $template->setFrom($actor_handle->getPHID());
    }

    $template
      ->setSubject($subject)
      ->setVarySubject($vary_subject)
      ->setBody($body)
      ->setIsHTML($this->shouldMarkMailAsHTML())
      ->setParentMessageID($this->parentMessageID)
      ->addHeader('Thread-Topic', $this->getRevision()->getTitle());

    $template->setAttachments($attachments);

    $template->setThreadID(
      $this->getThreadID(),
      $this->isFirstMailAboutRevision());

    if ($this->heraldRulesHeader) {
      $template->addHeader('X-Herald-Rules', $this->heraldRulesHeader);
    }

    $revision = $this->revision;
    if ($revision) {
      if ($revision->getAuthorPHID()) {
        $template->addHeader(
          'X-Differential-Author',
          '<'.$revision->getAuthorPHID().'>');
      }
      if ($revision->getReviewers()) {
        $template->addHeader(
          'X-Differential-Reviewers',
          '<'.implode('>, <', $revision->getReviewers()).'>');
      }
      if ($revision->getCCPHIDs()) {
        $template->addHeader(
          'X-Differential-CCs',
          '<'.implode('>, <', $revision->getCCPHIDs()).'>');

        // Determine explicit CCs (those added by humans) and put them in a
        // header so users can differentiate between Herald CCs and human CCs.

        $relation_subscribed = DifferentialRevision::RELATION_SUBSCRIBED;
        $raw = $revision->getRawRelations($relation_subscribed);

        $reason_phids = ipull($raw, 'reasonPHID');
        $reason_handles = id(new PhabricatorObjectHandleData($reason_phids))
          ->loadHandles();

        $explicit_cc = array();
        foreach ($raw as $relation) {
          if (!$relation['reasonPHID']) {
            continue;
          }
          $type = $reason_handles[$relation['reasonPHID']]->getType();
          if ($type == PhabricatorPHIDConstants::PHID_TYPE_USER) {
            $explicit_cc[] = $relation['objectPHID'];
          }
        }

        if ($explicit_cc) {
          $template->addHeader(
            'X-Differential-Explicit-CCs',
            '<'.implode('>, <', $explicit_cc).'>');
        }
      }
    }

    $template->setIsBulk(true);
    $template->setRelatedPHID($this->getRevision()->getPHID());

    $mailtags = $this->getMailTags();
    if ($mailtags) {
      $template->setMailTags($mailtags);
    }

    $phids = array();
    foreach ($to_phids as $phid) {
      $phids[$phid] = true;
    }
    foreach ($cc_phids as $phid) {
      $phids[$phid] = true;
    }
    $phids = array_keys($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_DIFFERENTIAL_WILLSENDMAIL,
      array(
        'mail' => $template,
      )
    );
    PhutilEventEngine::dispatchEvent($event);

    $template = $event->getValue('mail');

    $mails = $reply_handler->multiplexMail(
      $template,
      array_select_keys($handles, $to_phids),
      array_select_keys($handles, $cc_phids));

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }
  }

  protected function getMailTags() {
    return array();
  }

  protected function getSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.differential.subject-prefix');
  }

  protected function buildSubject() {
    return trim($this->getSubjectPrefix().' '.$this->renderSubject());
  }

  protected function buildVarySubject() {
    return trim($this->getSubjectPrefix().' '.$this->renderVarySubject());
  }

  protected function shouldMarkMailAsHTML() {
    return false;
  }

  protected function buildBody() {

    $body = $this->renderBody();

    $reply_handler = $this->getReplyHandler();
    $reply_instructions = $reply_handler->getReplyHandlerInstructions();
    if ($reply_instructions) {
      $body .=
        "\nREPLY HANDLER ACTIONS\n".
        "  {$reply_instructions}\n";
    }

    if ($this->getHeraldTranscriptURI() && $this->isFirstMailToRecipients()) {
      $manage_uri = PhabricatorEnv::getProductionURI(
        '/herald/view/differential/');

      $xscript_uri = $this->getHeraldTranscriptURI();
      $body .= <<<EOTEXT

MANAGE HERALD DIFFERENTIAL RULES
  {$manage_uri}

WHY DID I GET THIS EMAIL?
  {$xscript_uri}

EOTEXT;
    }

    return $body;
  }

  /**
   * You can override this method in a subclass and return array of attachments
   * to be sent with the email.  Each attachment is an instance of
   * PhabricatorMetaMTAAttachment.
   */
  protected function buildAttachments() {
    return array();
  }

  public function getReplyHandler() {
    if (!$this->replyHandler) {
      $this->replyHandler =
        self::newReplyHandlerForRevision($this->getRevision());
    }

    return $this->replyHandler;
  }

  public static function newReplyHandlerForRevision(
    DifferentialRevision $revision) {

    $reply_handler = PhabricatorEnv::newObjectFromConfig(
      'metamta.differential.reply-handler');
    $reply_handler->setMailReceiver($revision);

    return $reply_handler;
  }


  protected function formatText($text) {
    $text = explode("\n", $text);
    foreach ($text as &$line) {
      $line = rtrim('  '.$line);
    }
    unset($line);
    return implode("\n", $text);
  }

  public function setToPHIDs(array $to) {
    $this->to = $this->filterContactPHIDs($to);
    return $this;
  }

  public function setCCPHIDs(array $cc) {
    $this->cc = $this->filterContactPHIDs($cc);
    return $this;
  }

  protected function filterContactPHIDs(array $phids) {
    return $phids;

    // TODO: actually do this?

    // Differential revisions use Subscriptions for CCs, so any arbitrary
    // PHID can end up CC'd to them. Only try to actually send email PHIDs
    // which have ToolsHandle types that are marked emailable. If we don't
    // filter here, sending the email will fail.
/*
    $handles = array();
    prep(new ToolsHandleData($phids, $handles));
    foreach ($handles as $phid => $handle) {
      if (!$handle->isEmailable()) {
        unset($handles[$phid]);
      }
    }
    return array_keys($handles);
*/
  }

  protected function getToPHIDs() {
    return $this->to;
  }

  protected function getCCPHIDs() {
    return $this->cc;
  }

  public function setRevision($revision) {
    $this->revision = $revision;
    return $this;
  }

  public function getRevision() {
    return $this->revision;
  }

  protected function getThreadID() {
    $phid = $this->getRevision()->getPHID();
    return "differential-rev-{$phid}-req";
  }

  public function setComment($comment) {
    $this->comment = $comment;
    return $this;
  }

  public function getComment() {
    return $this->comment;
  }

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function getChangesets() {
    return $this->changesets;
  }

  protected function getManiphestTaskPHIDs() {
    return $this->getRevision()->getAttachedPHIDs(
      PhabricatorPHIDConstants::PHID_TYPE_TASK);
  }

  public function setInlineComments(array $inline_comments) {
    assert_instances_of($inline_comments, 'PhabricatorInlineCommentInterface');
    $this->inlineComments = $inline_comments;
    return $this;
  }

  public function getInlineComments() {
    return $this->inlineComments;
  }

  public function renderRevisionDetailLink() {
    $uri = $this->getRevisionURI();
    return "REVISION DETAIL\n  {$uri}";
  }

  public function getRevisionURI() {
    return PhabricatorEnv::getProductionURI('/D'.$this->getRevision()->getID());
  }

  public function setIsFirstMailToRecipients($first) {
    $this->isFirstMailToRecipients = $first;
    return $this;
  }

  public function isFirstMailToRecipients() {
    return $this->isFirstMailToRecipients;
  }

  public function setIsFirstMailAboutRevision($first) {
    $this->isFirstMailAboutRevision = $first;
    return $this;
  }

  public function isFirstMailAboutRevision() {
    return $this->isFirstMailAboutRevision;
  }

  public function setHeraldTranscriptURI($herald_transcript_uri) {
    $this->heraldTranscriptURI = $herald_transcript_uri;
    return $this;
  }

  public function getHeraldTranscriptURI() {
    return $this->heraldTranscriptURI;
  }

  protected function renderHandleList(array $handles, array $phids) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $names = array();
    foreach ($phids as $phid) {
      $names[] = $handles[$phid]->getName();
    }
    return implode(', ', $names);
  }

}
