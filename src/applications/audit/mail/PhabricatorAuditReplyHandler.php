<?php

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
