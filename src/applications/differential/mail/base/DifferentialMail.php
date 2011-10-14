<?php

/*
 * Copyright 2011 Facebook, Inc.
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

  abstract protected function renderSubject();
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

    $cc_phids    = $this->getCCPHIDs();
    $subject     = $this->buildSubject();
    $body        = $this->buildBody();
    $attachments = $this->buildAttachments();

    $template = new PhabricatorMetaMTAMail();
    $actor_handle = $this->getActorHandle();
    $reply_handler = $this->getReplyHandler();

    if ($actor_handle) {
      $template->setFrom($actor_handle->getPHID());
    }

    $template
      ->setSubject($subject)
      ->setBody($body)
      ->setIsHTML($this->shouldMarkMailAsHTML())
      ->setParentMessageID($this->parentMessageID)
      ->addHeader('Thread-Topic', $this->getRevision()->getTitle());

    foreach ($attachments as $attachment) {
      $template->addAttachment(
        $attachment['data'],
        $attachment['filename'],
        $attachment['mimetype']
      );
    }

    $template->setThreadID(
      $this->getThreadID(),
      $this->isFirstMailAboutRevision());

    if ($this->heraldRulesHeader) {
      $template->addHeader('X-Herald-Rules', $this->heraldRulesHeader);
    }

    $template->setRelatedPHID($this->getRevision()->getPHID());

    $phids = array();
    foreach ($to_phids as $phid) {
      $phids[$phid] = true;
    }
    foreach ($cc_phids as $phid) {
      $phids[$phid] = true;
    }
    $phids = array_keys($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $mails = $reply_handler->multiplexMail(
      $template,
      array_select_keys($handles, $to_phids),
      array_select_keys($handles, $cc_phids));

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }
  }

  protected function getSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.differential.subject-prefix');
  }

  protected function buildSubject() {
    return trim($this->getSubjectPrefix().' '.$this->renderSubject());
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

Tip: use the X-Herald-Rules header to filter Herald messages in your client.

EOTEXT;
    }

    return $body;
  }

  /**
   * You can override this method in a subclass and return array of attachments
   * to be sent with the email.  Each attachment is a dictionary with 'data',
   * 'filename' and 'mimetype' keys.  For example:
   *
   *   array(
   *     'data' => 'some text',
   *     'filename' => 'example.txt',
   *     'mimetype' => 'text/plain'
   *   );
   */
  protected function buildAttachments() {
    return array();
  }

  public function getReplyHandler() {
    if ($this->replyHandler) {
      return $this->replyHandler;
    }

    $handler_class = PhabricatorEnv::getEnvConfig(
      'metamta.differential.reply-handler');

    $reply_handler = self::newReplyHandlerForRevision($this->getRevision());

    $this->replyHandler = $reply_handler;

    return $this->replyHandler;
  }

  public static function newReplyHandlerForRevision(
    DifferentialRevision $revision) {

    $handler_class = PhabricatorEnv::getEnvConfig(
      'metamta.differential.reply-handler');

    $reply_handler = newv($handler_class, array());
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
    $domain = PhabricatorEnv::getEnvConfig('metamta.domain');
    return "<differential-rev-{$phid}-req@{$domain}>";
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
    $names = array();
    foreach ($phids as $phid) {
      $names[] = $handles[$phid]->getName();
    }
    return implode(', ', $names);
  }

}
