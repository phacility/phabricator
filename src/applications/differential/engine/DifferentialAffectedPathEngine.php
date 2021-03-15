<?php

final class DifferentialAffectedPathEngine
  extends Phobject {

  private $revision;
  private $diff;

  public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  public function getRevision() {
    return $this->revision;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->diff;
  }

  public function updateAffectedPaths() {
    $revision = $this->getRevision();
    $diff = $this->getDiff();
    $repository = $revision->getRepository();

    if ($repository) {
      $repository_id = $repository->getID();
    } else {
      $repository_id = null;
    }

    $paths = $this->getAffectedPaths();

    $path_ids =
      PhabricatorRepositoryCommitChangeParserWorker::lookupOrCreatePaths(
        $paths);

    $table = new DifferentialAffectedPath();
    $conn = $table->establishConnection('w');

    $sql = array();
    foreach ($path_ids as $path_id) {
      $sql[] = qsprintf(
        $conn,
        '(%nd, %d, %d)',
        $repository_id,
        $path_id,
        $revision->getID());
    }

    queryfx(
      $conn,
      'DELETE FROM %R WHERE revisionID = %d',
      $table,
      $revision->getID());
    if ($sql) {
      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn,
          'INSERT INTO %R (repositoryID, pathID, revisionID) VALUES %LQ',
          $table,
          $chunk);
      }
    }
  }

  public function destroyAffectedPaths() {
    $revision = $this->getRevision();

    $table = new DifferentialAffectedPath();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'DELETE FROM %R WHERE revisionID = %d',
      $table,
      $revision->getID());
  }

  public function getAffectedPaths() {
    $revision = $this->getRevision();
    $diff = $this->getDiff();
    $repository = $revision->getRepository();

    $path_prefix = null;
    if ($repository) {
      $local_root = $diff->getSourceControlPath();
      if ($local_root) {
        // We're in a working copy which supports subdirectory checkouts (e.g.,
        // SVN) so we need to figure out what prefix we should add to each path
        // (e.g., trunk/projects/example/) to get the absolute path from the
        // root of the repository. DVCS systems like Git and Mercurial are not
        // affected.

        // Normalize both paths and check if the repository root is a prefix of
        // the local root. If so, throw it away. Note that this correctly
        // handles the case where the remote path is "/".
        $local_root = id(new PhutilURI($local_root))->getPath();
        $local_root = rtrim($local_root, '/');

        $repo_root = id(new PhutilURI($repository->getRemoteURI()))->getPath();
        $repo_root = rtrim($repo_root, '/');

        if (!strncmp($repo_root, $local_root, strlen($repo_root))) {
          $path_prefix = substr($local_root, strlen($repo_root));
        }
      }
    }

    $changesets = $diff->getChangesets();

    $paths = array();
    foreach ($changesets as $changeset) {
      $paths[] = $path_prefix.'/'.$changeset->getFilename();
    }

    // Mark this as also touching all parent paths, so you can see all pending
    // changes to any file within a directory.
    $all_paths = array();
    foreach ($paths as $local) {
      foreach (DiffusionPathIDQuery::expandPathToRoot($local) as $path) {
        $all_paths[$path] = true;
      }
    }
    $all_paths = array_keys($all_paths);

    return $all_paths;
  }

}
