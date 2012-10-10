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
  protected $excludePHIDs = array();

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

  abstract protected function renderVaryPrefix();
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
    $attachments  = $this->buildAttachments();

    $template = new PhabricatorMetaMTAMail();
    $actor_handle = $this->getActorHandle();
    $reply_handler = $this->getReplyHandler();

    if ($actor_handle) {
      $template->setFrom($actor_handle->getPHID());
    }

    $template
      ->setIsHTML($this->shouldMarkMailAsHTML())
      ->setParentMessageID($this->parentMessageID)
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->addHeader('Thread-Topic', $this->getThreadTopic());

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

      $reviewer_phids = $revision->getReviewers();
      if ($reviewer_phids) {
        // Add several headers to support e-mail clients which are not able to
        // create rules using regular expressions or wildcards (namely Outlook).
        $template->addPHIDHeaders('X-Differential-Reviewer', $reviewer_phids);

        // Add it also as a list to allow matching of the first reviewer and
        // also for backwards compatibility.
        $template->addHeader(
          'X-Differential-Reviewers',
          '<'.implode('>, <', $reviewer_phids).'>');
      }

      if ($cc_phids) {
        $template->addPHIDHeaders('X-Differential-CC', $cc_phids);
        $template->addHeader(
          'X-Differential-CCs',
          '<'.implode('>, <', $cc_phids).'>');

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
          $template->addPHIDHeaders('X-Differential-Explicit-CC', $explicit_cc);
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
    $objects = id(new PhabricatorObjectHandleData($phids))->loadObjects();

    $to_handles = array_select_keys($handles, $to_phids);
    $cc_handles = array_select_keys($handles, $cc_phids);

    $this->prepareBody();

    $mails = $reply_handler->multiplexMail($template, $to_handles, $cc_handles);

    $original_translator = PhutilTranslator::getInstance();
    if (!PhabricatorMetaMTAMail::shouldMultiplexAllMail()) {
      $translation = PhabricatorEnv::newObjectFromConfig(
        'translation.provider');
      $translator = id(new PhutilTranslator())
        ->setLanguage($translation->getLanguage())
        ->addTranslations($translation->getTranslations());
    }

    try {
      foreach ($mails as $mail) {
        if (PhabricatorMetaMTAMail::shouldMultiplexAllMail()) {
          $translation = newv($mail->getTranslation($objects), array());
          $translator = id(new PhutilTranslator())
            ->setLanguage($translation->getLanguage())
            ->addTranslations($translation->getTranslations());
          PhutilTranslator::setInstance($translator);
        }

        $body =
          $this->buildBody()."\n".
          $reply_handler->getRecipientsSummary($to_handles, $cc_handles);

        $mail
          ->setSubject($this->renderSubject())
          ->setSubjectPrefix($this->getSubjectPrefix())
          ->setVarySubjectPrefix($this->renderVaryPrefix())
          ->setBody($body);

        $event = new PhabricatorEvent(
          PhabricatorEventType::TYPE_DIFFERENTIAL_WILLSENDMAIL,
          array(
            'mail' => $mail,
          )
        );
        PhutilEventEngine::dispatchEvent($event);
        $mail = $event->getValue('mail');

        $mail->saveAndSend();
      }

    } catch (Exception $ex) {
      PhutilTranslator::setInstance($original_translator);
      throw $ex;
    }

    PhutilTranslator::setInstance($original_translator);
  }

  protected function getMailTags() {
    return array();
  }

  protected function getSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.differential.subject-prefix');
  }

  protected function shouldMarkMailAsHTML() {
    return false;
  }

  /**
   * @{method:buildBody} is called once for each e-mail recipient to allow
   * translating text to his language. This method can be used to load data that
   * don't need translation and use them later in @{method:buildBody}.
   *
   * @param
   * @return
   */
  protected function prepareBody() {
  }

  protected function buildBody() {
    $main_body = $this->renderBody();

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($main_body);

    $reply_handler = $this->getReplyHandler();
    $body->addReplySection($reply_handler->getReplyHandlerInstructions());

    if ($this->getHeraldTranscriptURI() && $this->isFirstMailToRecipients()) {
      $manage_uri = '/herald/view/differential/';
      $xscript_uri = $this->getHeraldTranscriptURI();
      $body->addHeraldSection($manage_uri, $xscript_uri);
    }

    return $body->render();
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
    $text = explode("\n", rtrim($text));
    foreach ($text as &$line) {
      $line = rtrim('  '.$line);
    }
    unset($line);
    return implode("\n", $text);
  }

  public function setExcludeMailRecipientPHIDs(array $exclude) {
    $this->excludePHIDs = $exclude;
    return $this;
  }

  public function getExcludeMailRecipientPHIDs() {
    return $this->excludePHIDs;
  }

  public function setToPHIDs(array $to) {
    $this->to = $to;
    return $this;
  }

  public function setCCPHIDs(array $cc) {
    $this->cc = $cc;
    return $this;
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

  protected function getThreadTopic() {
    $id = $this->getRevision()->getID();
    $title = $this->getRevision()->getOriginalTitle();
    return "D{$id}: {$title}";
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

  public function setInlineComments(array $inline_comments) {
    assert_instances_of($inline_comments, 'PhabricatorInlineCommentInterface');
    $this->inlineComments = $inline_comments;
    return $this;
  }

  public function getInlineComments() {
    return $this->inlineComments;
  }

  protected function renderAuxFields($phase) {
    $selector = DifferentialFieldSelector::newSelector();
    $aux_fields = $selector->sortFieldsForMail(
      $selector->getFieldSpecifications());

    $body = array();
    foreach ($aux_fields as $field) {
      $field->setRevision($this->getRevision());
      // TODO: Introduce and use getRequiredHandlePHIDsForMail() and load all
      // handles in prepareBody().
      $text = $field->renderValueForMail($phase);
      if ($text !== null) {
        $body[] = $text;
        $body[] = null;
      }
    }

    return implode("\n", $body);
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
