<?php


class PublicEventPings {
  /** @var array<string, PublicPing> */
  private array $pingedUsers;

  public function __construct() {
    $this->pingedUsers = [];
  }

  public function fromMainComment(PhabricatorUser $target, EmailCommentMessage $message) {
    $ping = $this->pingedUsers[$target->getPHID()] ?? new PublicPing($target);
    $ping->setMainComment($message);
    $this->pingedUsers[$target->getPHID()] = $ping;
  }

  public function fromInlineComment(PhabricatorUser $target, EmailInlineComment $inlineComment) {
    $ping = $this->pingedUsers[$target->getPHID()] ?? new PublicPing($target);
    $ping->appendInlineComment($inlineComment);
    $this->pingedUsers[$target->getPHID()] = $ping;
  }

  /**
   * @return EmailRevisionCommentPinged[]
   */
  public function intoBodies(string $actorEmail, string $transactionLink): array {
    $associative = array_filter(array_map(function(PublicPing $ping) use ($actorEmail, $transactionLink) {
      return $ping->intoPublicBody($actorEmail, $transactionLink);
    }, $this->pingedUsers));
    return array_values($associative);
  }
}