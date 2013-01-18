<?php

final class PhabricatorOwnersPackagePathValidator {

  /*
   * If a file/directory was moved the paths in owners package become stale.
   * This method updates the stale paths in the owners packages to their new
   * paths.
   */
  public static function updateOwnersPackagePaths(
    PhabricatorRepositoryCommit $commit) {
    $changes = self::loadDiffusionChangesForCommit($commit);

    if (!$changes) {
      return;
    }

    $repository =
      id(new PhabricatorRepository())->load($commit->getRepositoryID());
    $move_map = array();
    foreach ($changes as $change) {
      if ($change->getChangeType() == DifferentialChangeType::TYPE_MOVE_HERE) {
        $from_path = "/".$change->getTargetPath();
        $to_path = "/".$change->getPath();
        if ($change->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
          $to_path = $to_path."/";
          $from_path = $from_path."/";
        }
        $move_map[$from_path] = $to_path;
      }
    }

    if ($move_map) {
      self::updateAffectedPackages($repository, $move_map);
    }
  }

  private static function updateAffectedPackages($repository, array $move_map) {
    $paths = array_keys($move_map);
    if ($paths) {
      $packages = PhabricatorOwnersPackage::loadAffectedPackages($repository,
        $paths);
      foreach ($packages as $package) {
        self::updatePackagePaths($package, $move_map);
      }
    }
  }

  private static function updatePackagePaths($package, array $move_map) {
    $paths = array_keys($move_map);
    $pkg_paths = $package->loadPaths();
    $new_paths = array();
    foreach ($pkg_paths as $pkg_path) {
      $path_changed = false;

      foreach ($paths as $old_path) {
        if (strncmp($pkg_path->getPath(), $old_path, strlen($old_path)) === 0) {
          $new_paths[] = array (
            'packageID' => $package->getID(),
            'repositoryPHID' => $pkg_path->getRepositoryPHID(),
            'path' => str_replace($pkg_path->getPath(), $old_path,
                                    $move_map[$old_path])
          );
          $path_changed = true;
        }
      }

      if (!$path_changed) {
        $new_paths[] = array (
          'packageID' => $package->getID(),
          'repositoryPHID' => $pkg_path->getRepositoryPHID(),
          'path' => $pkg_path->getPath(),
        );
      }
    }

    if ($new_paths) {
      $package->attachOldPrimaryOwnerPHID($package->getPrimaryOwnerPHID());
      $package->attachUnsavedPaths($new_paths);
      $package->save(); // save the changes and notify the owners.
    }
  }

  private static function loadDiffusionChangesForCommit($commit) {
    $repository =
      id(new PhabricatorRepository())->load($commit->getRepositoryID());
    $data = array(
      'repository'=>$repository,
      'commit'=>$commit->getCommitIdentifier()
    );
    $drequest = DiffusionRequest::newFromDictionary($data);
    $change_query =
      DiffusionPathChangeQuery::newFromDiffusionRequest($drequest);
    return $change_query->loadChanges();
  }
}
