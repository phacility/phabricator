<?php

final class PhabricatorOwnerPathQuery {

  public static function loadAffectedPaths(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $repository,
        'commit'      => $commit->getCommitIdentifier(),
      ));

    $path_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $paths = $path_query->loadChanges();

    $result = array();
    foreach ($paths as $path) {
      $basic_path = '/' . $path->getPath();
      if ($path->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
        $basic_path = rtrim($basic_path, '/') . '/';
      }
      $result[] = $basic_path;
    }
    return $result;
  }

}
