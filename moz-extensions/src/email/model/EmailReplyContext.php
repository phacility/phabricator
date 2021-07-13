<?php


class EmailReplyContext {
  public string $otherAuthor;
  public string $otherDateUtc;
  public EmailCommentMessage $otherCommentMessage;

  public function __construct(string $otherAuthor, DateTime $otherDateUtc, EmailCommentMessage $message) {
    $this->otherAuthor = $otherAuthor;
    $this->otherDateUtc = $otherDateUtc->format(DateTime::ATOM);
    $this->otherCommentMessage = $message;
  }


}