<?php


class SecureEmailRevisionCreated implements SecureEmailBody
{
  /** @var EmailReviewer[] */
  public array $reviewers;
  /** @var EmailRecipient[] */
  public array $subscribers;

  /**
   * @param EmailReviewer[] $reviewers
   * @param EmailRecipient[] $subscribers
   */
  public function __construct(array $reviewers, array $subscribers)
  {
    $this->reviewers = $reviewers;
    $this->subscribers = $subscribers;
  }

}