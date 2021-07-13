<?php


class EmailRevisionCreated implements PublicEmailBody
{
  /** @var EmailAffectedFile[] */
  public array $affectedFiles;
  /** @var EmailReviewer[] */
  public array $reviewers;
  /** @var EmailRecipient[] */
  public array $subscribers;

  /**
   * @param EmailAffectedFile[] $affectedFiles
   * @param EmailReviewer[] $reviewers
   * @param EmailRecipient[] $subscribers
   */
  public function __construct(array $affectedFiles, array $reviewers, array $subscribers)
  {
    $this->affectedFiles = $affectedFiles;
    $this->reviewers = $reviewers;
    $this->subscribers = $subscribers;
  }


}