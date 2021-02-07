<?php

abstract class PhabricatorRepositoryCommitChangeParserWorker
  extends PhabricatorRepositoryCommitParserWorker {

  protected function getImportStepFlag() {
    return PhabricatorRepositoryCommit::IMPORTED_CHANGE;
  }

  public function getRequiredLeaseTime() {
    // It can take a very long time to parse commits; some commits in the
    // Facebook repository affect many millions of paths. Acquire 24h leases.
    return phutil_units('24 hours in seconds');
  }

  abstract protected function parseCommitChanges(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit);

  protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $this->log("%s\n", pht('Parsing "%s"...', $commit->getMonogram()));

    $hint = $this->loadCommitHint($commit);
    if ($hint && $hint->isUnreadable()) {
      $this->log(
        pht(
          'This commit is marked as unreadable, so changes will not be '.
          'parsed.'));
      return;
    }

    if (!$this->shouldSkipImportStep()) {
      $results = $this->parseCommitChanges($repository, $commit);
      if ($results) {
        $this->writeCommitChanges($repository, $commit, $results);
      }

      $commit->writeImportStatusFlag($this->getImportStepFlag());

      PhabricatorSearchWorker::queueDocumentForIndexing($commit->getPHID());
    }

    $this->finishParse();
  }

  public function parseChangesForUnitTest(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {
    return $this->parseCommitChanges($repository, $commit);
  }

  public static function lookupOrCreatePaths(array $paths) {
    $repository = new PhabricatorRepository();
    $conn_w = $repository->establishConnection('w');

    $result_map = self::lookupPaths($paths);

    $missing_paths = array_fill_keys($paths, true);
    $missing_paths = array_diff_key($missing_paths, $result_map);
    $missing_paths = array_keys($missing_paths);

    if ($missing_paths) {
      foreach (array_chunk($missing_paths, 128) as $path_chunk) {
        $sql = array();
        foreach ($path_chunk as $path) {
          $sql[] = qsprintf($conn_w, '(%s, %s)', $path, md5($path));
        }
        queryfx(
          $conn_w,
          'INSERT IGNORE INTO %T (path, pathHash) VALUES %LQ',
          PhabricatorRepository::TABLE_PATH,
          $sql);
      }
      $result_map += self::lookupPaths($missing_paths);
    }

    return $result_map;
  }

  private static function lookupPaths(array $paths) {
    $repository = new PhabricatorRepository();
    $conn_w = $repository->establishConnection('w');

    $result_map = array();
    foreach (array_chunk($paths, 128) as $path_chunk) {
      $chunk_map = queryfx_all(
        $conn_w,
        'SELECT path, id FROM %T WHERE pathHash IN (%Ls)',
        PhabricatorRepository::TABLE_PATH,
        array_map('md5', $path_chunk));
      foreach ($chunk_map as $row) {
        $result_map[$row['path']] = $row['id'];
      }
    }
    return $result_map;
  }

  protected function finishParse() {
    $this->queueCommitTask('PhabricatorRepositoryCommitPublishWorker');
  }

  private function writeCommitChanges(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    array $changes) {

    $conn = $repository->establishConnection('w');

    $repository_id = (int)$repository->getID();
    $commit_id = (int)$commit->getID();

    $changes_sql = array();
    foreach ($changes as $change) {
      $changes_sql[] = qsprintf(
        $conn,
        '(%d, %d, %d, %nd, %nd, %d, %d, %d, %d)',
        $repository_id,
        (int)$change->getPathID(),
        $commit_id,
        nonempty((int)$change->getTargetPathID(), null),
        nonempty((int)$change->getTargetCommitID(), null),
        (int)$change->getChangeType(),
        (int)$change->getFileType(),
        (int)$change->getIsDirect(),
        (int)$change->getCommitSequence());
    }

    queryfx(
      $conn,
      'DELETE FROM %T WHERE commitID = %d',
      PhabricatorRepository::TABLE_PATHCHANGE,
      $commit_id);

    foreach (PhabricatorLiskDAO::chunkSQL($changes_sql) as $chunk) {
      queryfx(
        $conn,
        'INSERT INTO %T
          (repositoryID, pathID, commitID, targetPathID, targetCommitID,
            changeType, fileType, isDirect, commitSequence)
          VALUES %LQ',
        PhabricatorRepository::TABLE_PATHCHANGE,
        $chunk);
    }
  }

}
