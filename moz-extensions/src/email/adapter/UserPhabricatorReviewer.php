<?php


class UserPhabricatorReviewer implements PhabricatorReviewer {
  private PhabricatorUser $rawUser;

  public function __construct(PhabricatorUser $rawUser) {
    $this->rawUser = $rawUser;
  }

  public function name(): string {
    return $this->rawUser->getUserName();
  }

  public function toRecipients(string $actorEmail): array {
    return array_filter([EmailRecipient::from($this->rawUser, $actorEmail)]);
  }
}