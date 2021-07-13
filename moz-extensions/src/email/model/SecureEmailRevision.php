<?php


class SecureEmailRevision {
  public int $revisionId;
  public string $link;
  public SecureEmailBug $bug;

  public function __construct(int $revisionId, string $link, SecureEmailBug $bug) {
    $this->revisionId = $revisionId;
    $this->link = $link;
    $this->bug = $bug;
  }

  public static function from(DifferentialRevision $rawRevision, BugStore $bugStore): SecureEmailRevision
  {
    $bug = $bugStore->resolveBug($rawRevision);
    return new SecureEmailRevision(
      $rawRevision->getID(),
      PhabricatorEnv::getProductionURI($rawRevision->getURI()),
      $bug
    );
  }
}