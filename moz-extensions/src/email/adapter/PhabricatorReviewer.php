<?php


interface PhabricatorReviewer {
  public function name(): string;

  /**
   * @return EmailRecipient[]
   */
  public function toRecipients(string $actorEmail): array;

  /**
   * @return EmailRecipient[]
   */
  public function getWatchersAsRecipients(string $actorEmail): array;
}
