<?php


class PublicPing {
  public PhabricatorUser $targetUser;
  public ?EmailCommentMessage $mainCommentMessage;
  /** @var EmailInlineComment[] */
  public array $inlineComments;

  public function __construct(PhabricatorUser $targetUser) {
    $this->targetUser = $targetUser;
    $this->inlineComments = [];
    $this->mainCommentMessage = null;
  }

  public function setMainComment(EmailCommentMessage $message) {
    $this->mainCommentMessage = $message;
  }

  public function appendInlineComment(EmailInlineComment $inlineComment) {
    $this->inlineComments[] = $inlineComment;
  }

  public function intoPublicBody(string $actorEmail, string $transactionLink): ?EmailRevisionCommentPinged {
    $recipient = EmailRecipient::from($this->targetUser, $actorEmail);
    if (!$recipient) {
      return null;
    }

    return new EmailRevisionCommentPinged(
      $recipient,
      $transactionLink,
      $this->mainCommentMessage,
      $this->inlineComments
    );
  }
}