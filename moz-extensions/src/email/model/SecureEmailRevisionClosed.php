<?php


class SecureEmailRevisionClosed implements SecureEmailBody
{
  /** @var EmailRecipient[] */
  public array $subscribers;
  /** @var EmailRecipient[] */
  public array $reviewers;
  public ?EmailRecipient $author;
  public int $commentCount;
  public string $transactionLink;

  /**
   * @param EmailRecipient[] $subscribers
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient|null $author
   * @param int $commentCount
   * @param string $transactionLink
   */
  public function __construct(array $subscribers, array $reviewers, ?EmailRecipient $author, int $commentCount, string $transactionLink)
  {
    $this->subscribers = $subscribers;
    $this->reviewers = $reviewers;
    $this->author = $author;
    $this->commentCount = $commentCount;
    $this->transactionLink = $transactionLink;
  }

}