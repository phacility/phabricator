<?php

final class DiffusionSvnHistoryQuery extends DiffusionHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    $conn_r = $repository->establishConnection('r');

    $paths = queryfx_all(
      $conn_r,
      'SELECT id, path FROM %T WHERE pathHash IN (%Ls)',
      PhabricatorRepository::TABLE_PATH,
      array(md5('/'.trim($path, '/'))));
    $paths = ipull($paths, 'id', 'path');
    $path_id = idx($paths, '/'.trim($path, '/'));

    if (!$path_id) {
      return array();
    }

    $filter_query = '';
    if ($this->needDirectChanges) {
      if ($this->needChildChanges) {
        $type = DifferentialChangeType::TYPE_CHILD;
        $filter_query = 'AND (isDirect = 1 OR changeType = '.$type.')';
      } else {
        $filter_query = 'AND (isDirect = 1)';
      }
    }

    $history_data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T WHERE repositoryID = %d AND pathID = %d
        AND commitSequence <= %d
        %Q
        ORDER BY commitSequence DESC
        LIMIT %d, %d',
      PhabricatorRepository::TABLE_PATHCHANGE,
      $repository->getID(),
      $path_id,
      $commit ? $commit : 0x7FFFFFFF,
      $filter_query,
      $this->getOffset(),
      $this->getLimit());

    $commits = array();
    $commit_data = array();

    $commit_ids = ipull($history_data, 'commitID');
    if ($commit_ids) {
      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'id IN (%Ld)',
        $commit_ids);
      if ($commits) {
        $commit_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
          'commitID in (%Ld)',
          $commit_ids);
        $commit_data = mpull($commit_data, null, 'getCommitID');
      }
    }

    $history = array();
    foreach ($history_data as $row) {
      $item = new DiffusionPathChange();

      $commit = idx($commits, $row['commitID']);
      if ($commit) {
        $item->setCommit($commit);
        $item->setCommitIdentifier($commit->getCommitIdentifier());
        $data = idx($commit_data, $commit->getID());
        if ($data) {
          $item->setCommitData($data);
        }
      }

      $item->setChangeType($row['changeType']);
      $item->setFileType($row['fileType']);


      $history[] = $item;
    }

    return $history;
  }

}
