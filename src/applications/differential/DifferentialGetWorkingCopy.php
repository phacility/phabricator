<?php

/**
 * Can't find a good place for this, so I'm putting it in the most notably
 * wrong place.
 */
final class DifferentialGetWorkingCopy extends Phobject {

  /**
   * Creates and/or cleans a workspace for the requested repo.
   *
   * return ArcanistGitAPI
   */
  public static function getCleanGitWorkspace(
    PhabricatorRepository $repo) {

    $origin_path = $repo->getLocalPath();

    $path = rtrim($origin_path, '/');
    $path = $path.'__workspace';

    if (!Filesystem::pathExists($path)) {
      $repo->execxLocalCommand(
        'clone -- file://%s %s',
        $origin_path,
        $path);

      if (!$repo->isHosted()) {
        id(new ArcanistGitAPI($path))->execxLocal(
          'remote set-url origin %s',
          $repo->getRemoteURI());
      }
    }

    $workspace = new ArcanistGitAPI($path);
    $workspace->execxLocal('clean -f -d');
    $workspace->execxLocal('checkout master');
    $workspace->execxLocal('fetch');
    $workspace->execxLocal('reset --hard origin/master');
    $workspace->reloadWorkingCopy();

    return $workspace;
  }

  /**
   * Creates and/or cleans a workspace for the requested repo.
   *
   * return ArcanistMercurialAPI
   */
  public static function getCleanMercurialWorkspace(
    PhabricatorRepository $repo) {

    $origin_path = $repo->getLocalPath();

    $path = rtrim($origin_path, '/');
    $path = $path.'__workspace';

    if (!Filesystem::pathExists($path)) {
      $repo->execxLocalCommand(
        'clone -- file://%s %s',
        $origin_path,
        $path);
    }

    $workspace = new ArcanistMercurialAPI($path);
    $workspace->execxLocal('pull');
    $workspace->execxLocal('update --clean default');
    $workspace->reloadWorkingCopy();

    return $workspace;
  }

}
