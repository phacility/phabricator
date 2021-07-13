<?php


class SecureEventPings {
  /** @var array<string, PhabricatorUser> */
  private array $pingedUsers;

  public function __construct() {
    $this->pingedUsers = [];
  }

  public function add(PhabricatorUser $target) {
    $this->pingedUsers[$target->getPHID()] = $target;
  }

  /**
   * @return SecureEmailRevisionCommentPinged[]
   */
  public function intoBodies(string $actorEmail, string $transactionLink): array {
    $associative = array_filter(array_map(function(PhabricatorUser $target) use ($actorEmail, $transactionLink) {
      $recipient = EmailRecipient::from($target, $actorEmail);
      if (!$recipient) {
        return null;
      }

      return new SecureEmailRevisionCommentPinged($recipient, $transactionLink);
    }, $this->pingedUsers));
    return array_values($associative);
  }
}