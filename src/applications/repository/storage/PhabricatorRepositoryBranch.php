<?php

final class PhabricatorRepositoryBranch extends PhabricatorRepositoryDAO {

  protected $repositoryID;
  protected $name;
  protected $lintCommit;

  public static function loadBranch($repository_id, $branch_name) {
    return id(new PhabricatorRepositoryBranch())->loadOneWhere(
      'repositoryID = %d AND name = %s',
      $repository_id,
      $branch_name);
  }

  public static function loadOrCreateBranch($repository_id, $branch_name) {
    $branch = self::loadBranch($repository_id, $branch_name);
    if ($branch) {
      return $branch;
    }

    return id(new PhabricatorRepositoryBranch())
      ->setRepositoryID($repository_id)
      ->setName($branch_name)
      ->save();
  }

}
