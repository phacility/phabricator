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

  const SUBJECT_PREFIX  = '[Differential]';

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

  public function setXHeraldRulesHeader($header) {
    $this->heraldRulesHeader = $header;
    return $this;
  }

  public function send() {
    $to_phids = $this->getToPHIDs();
    if (!$to_phids) {
      throw new Exception('No "To:" users provided!');
    }

    $cc_phids = $this->getCCPHIDs();
    $subject  = $this->buildSubject();
    $body     = $this->buildBody();

    $mail = new PhabricatorMetaMTAMail();
    $actor_handle = $this->getActorHandle();
    $reply_handler = $this->getReplyHandler();

    if ($actor_handle) {
      $mail->setFrom($actor_handle->getPHID());
    }

    if ($reply_handler) {
      if ($actor_handle) {
        $actor = id(new PhabricatorUser())->loadOneWhere(
          'phid = %s',
          $actor_handle->getPHID());
        $reply_handler->setActor($actor);
      }

      $reply_to = $reply_handler->getReplyHandlerEmailAddress();
      if ($reply_to) {
        $mail->setReplyTo($reply_to);
      }
    }

    $mail
      ->addTos($to_phids)
      ->addCCs($cc_phids)
      ->setSubject($subject)
      ->setBody($body)
      ->setIsHTML($this->shouldMarkMailAsHTML())
      ->addHeader('Thread-Topic', $this->getRevision()->getTitle());

    $mail->setThreadID(
      $this->getThreadID(),
      $this->isFirstMailAboutRevision());

    if ($this->heraldRulesHeader) {
      $mail->addHeader('X-Herald-Rules', $this->heraldRulesHeader);
    }

    $mail->setRelatedPHID($this->getRevision()->getPHID());

    $mail->saveAndSend();
  }

  protected function buildSubject() {
    return self::SUBJECT_PREFIX.' '.$this->renderSubject();
  }

  protected function shouldMarkMailAsHTML() {
    return false;
  }

  protected function buildBody() {

    $body = $this->renderBody();

    $handler_body_text = $this->getReplyHandlerBodyText();
    if ($handler_body_text) {
      $body .= $handler_body_text;
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

  protected function getReplyHandlerBodyText() {
    $reply_handler = $this->getReplyHandler();

    if (!$reply_handler) {
      return null;
    }

    return $reply_handler->getBodyText();
  }

  protected function getReplyHandler() {
    if ($this->replyHandler) {
      return $this->replyHandler;
    }

    $reply_handler = self::loadReplyHandler();
    if (!$reply_handler) {
      return null;
    }

    $reply_handler->setRevision($this->getRevision());
    $this->replyHandler = $reply_handler;
    return $this->replyHandler;
  }

  public static function loadReplyHandler() {
    if (!PhabricatorEnv::getEnvConfig('phabricator.enable-reply-handling')) {
      return null;
    }

    $reply_handler = PhabricatorEnv::getEnvConfig('differential.replyhandler');

    if (!$reply_handler) {
      return null;
    }

    PhutilSymbolLoader::loadClass($reply_handler);
    $reply_handler = newv($reply_handler, array());
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

}
