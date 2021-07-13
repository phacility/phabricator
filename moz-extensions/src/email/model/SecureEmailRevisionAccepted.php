<?php


class SecureEmailRevisionAccepted implements SecureEmailBody
{
  public string $landoLink;
  public bool $isReadyToLand;
  /** @var EmailRecipient[] */
  public array $subscribers;
  /** @var EmailRecipient[] */
  public array $reviewers;
  public ?EmailRecipient $author;
  public int $commentCount;
  public string $transactionLink;

  /**
   * @param string $landoLink
   * @param bool $isReadyToLand
   * @param EmailRecipient[] $subscribers
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient|null $author
   * @param int $commentCount
   * @param string $transactionLink
   */
  public function __construct(string $landoLink, bool $isReadyToLand, array $subscribers, array $reviewers, ?EmailRecipient $author, int $commentCount, string $transactionLink)
  {
    $this->landoLink = $landoLink;
    $this->isReadyToLand = $isReadyToLand;
    $this->subscribers = $subscribers;
    $this->reviewers = $reviewers;
    $this->author = $author;
    $this->commentCount = $commentCount;
    $this->transactionLink = $transactionLink;
  }


}