<?php

abstract class DiffusionQuery {

  private $request;

  final protected function __construct() {
    // <protected>
  }

  protected static function newQueryObject(
    $base_class,
    DiffusionRequest $request) {

    $repository = $request->getRepository();

    $map = array(
      PhabricatorRepositoryType::REPOSITORY_TYPE_GIT        => 'Git',
      PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL  => 'Mercurial',
      PhabricatorRepositoryType::REPOSITORY_TYPE_SVN        => 'Svn',
    );

    $name = idx($map, $repository->getVersionControlSystem());
    if (!$name) {
      throw new Exception("Unsupported VCS!");
    }

    $class = str_replace('Diffusion', 'Diffusion'.$name, $base_class);
    $obj = new $class();
    $obj->request = $request;

    return $obj;
  }

  final protected function getRequest() {
    return $this->request;
  }

  abstract protected function executeQuery();


/* -(  Query Utilities  )---------------------------------------------------- */


  final protected function loadCommitsByIdentifiers(array $identifiers) {
    if (!$identifiers) {
      return array();
    }

    $commits = array();
    $commit_data = array();

    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
      'repositoryID = %d AND commitIdentifier IN (%Ls)',
        $repository->getID(),
      $identifiers);
    $commits = mpull($commits, null, 'getCommitIdentifier');

    // Build empty commit objects for every commit, so we can show unparsed
    // commits in history views as "unparsed" instead of not showing them. This
    // makes the process of importing and parsing commits much clearer to the
    // user.

    $commit_list = array();
    foreach ($identifiers as $identifier) {
      $commit_obj = idx($commits, $identifier);
      if (!$commit_obj) {
        $commit_obj = new PhabricatorRepositoryCommit();
        $commit_obj->setRepositoryID($repository->getID());
        $commit_obj->setCommitIdentifier($identifier);
        $commit_obj->setIsUnparsed(true);
        $commit_obj->makeEphemeral();
      }
      $commit_list[$identifier] = $commit_obj;
    }
    $commits = $commit_list;

    $commit_ids = array_filter(mpull($commits, 'getID'));
    if ($commit_ids) {
      $commit_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
        'commitID in (%Ld)',
        $commit_ids);
      $commit_data = mpull($commit_data, null, 'getCommitID');
    }

    foreach ($commits as $commit) {
      if (!$commit->getID()) {
        continue;
      }
      if (idx($commit_data, $commit->getID())) {
        $commit->attachCommitData($commit_data[$commit->getID()]);
      }
    }

    return $commits;
  }

  final protected function loadHistoryForCommitIdentifiers(array $identifiers) {
    if (!$identifiers) {
      return array();
    }

    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();
    $commits = self::loadCommitsByIdentifiers($identifiers);

    if (!$commits) {
      return array();
    }

    $path = $drequest->getPath();

    $conn_r = $repository->establishConnection('r');

    $path_normal = DiffusionPathIDQuery::normalizePath($path);
    $paths = queryfx_all(
      $conn_r,
      'SELECT id, path FROM %T WHERE pathHash IN (%Ls)',
      PhabricatorRepository::TABLE_PATH,
      array(md5($path_normal)));
    $paths = ipull($paths, 'id', 'path');
    $path_id = idx($paths, $path_normal);

    $commit_ids = array_filter(mpull($commits, 'getID'));

    $path_changes = array();
    if ($path_id && $commit_ids) {
      $path_changes = queryfx_all(
        $conn_r,
        'SELECT * FROM %T WHERE commitID IN (%Ld) AND pathID = %d',
        PhabricatorRepository::TABLE_PATHCHANGE,
        $commit_ids,
        $path_id);
      $path_changes = ipull($path_changes, null, 'commitID');
    }

    $history = array();
    foreach ($identifiers as $identifier) {
      $item = new DiffusionPathChange();
      $item->setCommitIdentifier($identifier);
      $commit = idx($commits, $identifier);
      if ($commit) {
        $item->setCommit($commit);
        try {
          $item->setCommitData($commit->getCommitData());
        } catch (Exception $ex) {
          // Ignore, commit just doesn't have data.
        }
        $change = idx($path_changes, $commit->getID());
        if ($change) {
          $item->setChangeType($change['changeType']);
          $item->setFileType($change['fileType']);
        }
      }
      $history[] = $item;
    }

    return $history;
  }
}
