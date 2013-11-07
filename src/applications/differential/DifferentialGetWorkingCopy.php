<?php

/**
 * Can't find a good place for this, so I'm putting it in the most notably
 * wrong place.
 */
final class DifferentialGetWorkingCopy {

  /**
   * Creates and/or cleans a workspace for the requested repo.
   *
   * return ArcanistGitAPI
   */
  public static function getCleanGitWorkspace(
    PhabricatorRepository $repo) {

    $origin_path = $repo->getLocalPath();

    $path = rtrim($origin_path, '/');
    $path = $path . '__workspace';

    if (!Filesystem::pathExists($path)) {
      $repo->execxLocalCommand(
        'clone -- file://%s %s',
        $origin_path,
        $path);
    }

    $workspace = new ArcanistGitAPI($path);
    $workspace->execxLocal('clean -f -d');
    $workspace->execxLocal('checkout master');
    $workspace->execxLocal('fetch');
    $workspace->execxLocal('reset --hard origin/master');
    $workspace->reloadWorkingCopy();

    return $workspace;
  }

}
