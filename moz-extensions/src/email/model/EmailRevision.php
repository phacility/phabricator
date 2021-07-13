<?php


class EmailRevision {
  public int $revisionId;
  public string $name;
  public string $link;
  public string $repositoryName;
  public ?EmailBug $bug;

  public function __construct(int $revisionId, string $name, string $link, string $repositoryName, ?EmailBug $bug) {
    $this->revisionId = $revisionId;
    $this->name = $name;
    $this->link = $link;
    $this->repositoryName = $repositoryName;
    $this->bug = $bug;
  }

  public static function from(DifferentialRevision $rawRevision, BugStore $bugStore, ResolveRepositoryDetails $resolveRepositoryDetails): EmailRevision
  {
    $secureBug = $bugStore->resolveBug($rawRevision);
    if (!$secureBug) {
      $bug = null;
    } else {
      $bugName = $bugStore->queryName($secureBug->bugId) ?? '(failed to fetch bug name)';
      $bug = new EmailBug($secureBug->bugId, $bugName, $secureBug->link);
    }

    return new EmailRevision(
      $rawRevision->getID(),
      str_replace("\n", ' ', $rawRevision->getTitle()),
      PhabricatorEnv::getProductionURI($rawRevision->getURI()),
      $resolveRepositoryDetails->resolveRepoName($rawRevision->getRepositoryPHID()),
      $bug
    );
  }

}