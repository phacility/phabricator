<?php


class SecureEmailRevisionRequestedChanges implements SecureEmailBody
{
  public string $transactionLink;
  /** @var EmailRecipient[] */
  public array $subscribers;
  /** @var EmailRecipient[] */
  public array $reviewers;
  public ?EmailRecipient $author;
  public int $commentCount;

  /**
   * @param string $transactionLink
   * @param EmailRecipient[] $subscribers
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient|null $author
   * @param int $commentCount
   */
  public function __construct(string $transactionLink, array $subscribers, array $reviewers, ?EmailRecipient $author, int $commentCount)
  {
    $this->transactionLink = $transactionLink;
    $this->subscribers = $subscribers;
    $this->reviewers = $reviewers;
    $this->author = $author;
    $this->commentCount = $commentCount;
  }


}