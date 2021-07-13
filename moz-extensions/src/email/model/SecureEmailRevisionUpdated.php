<?php


class SecureEmailRevisionUpdated implements SecureEmailBody
{
  public string $landoLink;
  public string $newChangesLink;
  public bool $isReadyToLand;
  /** @var EmailReviewer[] */
  public array $reviewers;
  /** @var EmailRecipient[] */
  public array $subscribers;

  /**
   * @param string $landoLink
   * @param string $newChangesLink
   * @param bool $isReadyToLand
   * @param EmailReviewer[] $reviewers
   * @param EmailRecipient[] $subscribers
   */
  public function __construct(string $landoLink, string $newChangesLink, bool $isReadyToLand, array $reviewers, array $subscribers)
  {
    $this->landoLink = $landoLink;
    $this->newChangesLink = $newChangesLink;
    $this->isReadyToLand = $isReadyToLand;
    $this->reviewers = $reviewers;
    $this->subscribers = $subscribers;
  }


}