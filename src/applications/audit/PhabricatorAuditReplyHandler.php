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

/**
 * @group audit
 */
final class PhabricatorAuditReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorRepositoryCommit)) {
      throw new Exception("Mail receiver is not a commit!");
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'C');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('C');
  }

  public function getReplyHandlerDomain() {
    return PhabricatorEnv::getEnvConfig(
      'metamta.diffusion.reply-handler-domain');
  }

  public function getReplyHandlerInstructions() {
    if ($this->supportsReplies()) {
      return "Reply to comment.";
    } else {
      return null;
    }
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $commit = $this->getMailReceiver();
    $actor = $this->getActor();

    // TODO: Support !raise, !accept, etc.
    // TODO: Content sources.

    $comment = id(new PhabricatorAuditComment())
      ->setAction(PhabricatorAuditActionConstants::COMMENT)
      ->setContent($mail->getCleanTextBody());

    $editor = new PhabricatorAuditCommentEditor($commit);
    $editor->setActor($actor);
    $editor->setExcludeMailRecipientPHIDs(
      $this->getExcludeMailRecipientPHIDs());
    $editor->addComment($comment);
  }

}
