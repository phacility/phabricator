<?php


class GroupPhabricatorReviewer implements PhabricatorReviewer {
  private string $name;
  /** @var PhabricatorUser[] */
  private array $rawUsers;
  private array $rawWatcherUsers;

  /**
   * @param string $name
   * @param PhabricatorUser[] $rawUsers
   * @param PhabricatorUser[] $rawWatcherUsers
   */
  public function __construct(string $name, array $rawUsers, array $rawWatcherUsers) {
    $this->name = $name;
    $this->rawUsers = $rawUsers;
    $this->rawWatcherUsers = $rawWatcherUsers;
  }

  public function name(): string {
    return $this->name;
  }

  public function toRecipients(string $actorEmail): array {
    return array_values(array_filter(array_map(function($user) use ($actorEmail) {
      return EmailRecipient::from($user, $actorEmail);
    }, $this->rawUsers)));
  }

  public function getWatchersAsRecipients(string $actorEmail): array
  {
    return array_values(array_filter(array_map(function($user) use ($actorEmail) {
      return EmailRecipient::from($user, $actorEmail);
    }, $this->rawWatcherUsers)));
  }
}
