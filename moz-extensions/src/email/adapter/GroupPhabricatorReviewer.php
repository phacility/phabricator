<?php


class GroupPhabricatorReviewer implements PhabricatorReviewer {
  private string $name;
  /** @var PhabricatorUser[] */
  private array $rawUsers;

  /**
   * @param string $name
   * @param PhabricatorUser[] $rawUsers
   */
  public function __construct(string $name, array $rawUsers) {
    $this->name = $name;
    $this->rawUsers = $rawUsers;
  }

  public function name(): string {
    return $this->name;
  }

  public function toRecipients(string $actorEmail): array {
    return array_values(array_filter(array_map(function($user) use ($actorEmail) {
      return EmailRecipient::from($user, $actorEmail);
    }, $this->rawUsers)));
  }
}