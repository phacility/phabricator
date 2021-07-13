<?php


class MinimalEmailContext {
  public MinimalEmailRevision $revision;
  /** @var EmailRecipient[] */
  public array $recipients;

  /**
   * @param MinimalEmailRevision $revision
   * @param EmailRecipient[] $recipients
   */
  public function __construct(MinimalEmailRevision $revision, array $recipients)
  {
    $this->revision = $revision;
    $this->recipients = $recipients;
  }


}