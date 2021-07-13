<?php


class SecureEmailRevisionCommentPinged implements SecureEmailBody {
  public EmailRecipient $recipient;
  public string $transactionLink;

  public function __construct(EmailRecipient $recipient, string $transactionLink) {
    $this->recipient = $recipient;
    $this->transactionLink = $transactionLink;
  }
}