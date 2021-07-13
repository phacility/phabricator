<?php


class SecureEmailRevisionLanded implements SecureEmailBody
{
  /** @var EmailRecipient[] */
  public array $subscribers;
  /** @var EmailRecipient[] */
  public array $reviewers;
  public ?EmailRecipient $author;

  /**
   * @param EmailRecipient[] $subscribers
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient|null $author
   */
  public function __construct(array $subscribers, array $reviewers, ?EmailRecipient $author)
  {
    $this->subscribers = $subscribers;
    $this->reviewers = $reviewers;
    $this->author = $author;
  }

}