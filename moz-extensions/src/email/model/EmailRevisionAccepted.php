<?php


class EmailRevisionAccepted implements PublicEmailBody
{
  public ?EmailCommentMessage $mainCommentMessage;
  /** @var EmailInlineComment[] */
  public array $inlineComments;
  public string $transactionLink;
  public string $landoLink;
  public bool $isReadyToLand;
  /** @var EmailRecipient[] */
  public array $subscribers;
  /** @var EmailRecipient[] */
  public array $reviewers;
  public ?EmailRecipient $author;

  /**
   * @param EmailCommentMessage|null $mainCommentMessage
   * @param EmailInlineComment[] $inlineComments
   * @param string $transactionLink
   * @param string $landoLink
   * @param bool $isReadyToLand
   * @param EmailRecipient[] $subscribers
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient|null $author
   */
  public function __construct(?EmailCommentMessage $mainCommentMessage, array $inlineComments, string $transactionLink, string $landoLink, bool $isReadyToLand, array $subscribers, array $reviewers, ?EmailRecipient $author) {
    $this->mainCommentMessage = $mainCommentMessage;
    $this->inlineComments = $inlineComments;
    $this->transactionLink = $transactionLink;
    $this->landoLink = $landoLink;
    $this->isReadyToLand = $isReadyToLand;
    $this->subscribers = $subscribers;
    $this->reviewers = $reviewers;
    $this->author = $author;
  }
}