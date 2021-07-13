<?php


class MinimalEmailRevision {
  public int $revisionId;
  public string $link;

  public function __construct(int $revisionId, string $link)
  {
    $this->revisionId = $revisionId;
    $this->link = $link;
  }

  public static function from(DifferentialRevision $rawRevision): MinimalEmailRevision
  {
    return new MinimalEmailRevision(
      $rawRevision->getID(),
      PhabricatorEnv::getProductionURI($rawRevision->getURI()),
    );
  }

}