<?php


class SecureEmailRevisionAbandoned implements SecureEmailBody
{
  /** @var EmailRecipient[] */
  public array $reviewers;
  /** @var EmailRecipient[] */
  public array $subscribers;
  public int $commentCount;
  public string $transactionLink;

  /**
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient[] $subscribers
   * @param int $commentCount
   * @param string $transactionLink
   */
  public function __construct(array $reviewers, array $subscribers, int $commentCount, string $transactionLink) {
    $this->reviewers = $reviewers;
    $this->subscribers = $subscribers;
    $this->commentCount = $commentCount;
    $this->transactionLink = $transactionLink;
  }
}