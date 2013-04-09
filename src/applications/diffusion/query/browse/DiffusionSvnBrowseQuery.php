<?php

final class DiffusionSvnBrowseQuery extends DiffusionBrowseQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    $subpath = $repository->getDetail('svn-subpath');
    if ($subpath && strncmp($subpath, $path, strlen($subpath))) {
      // If we have a subpath and the path isn't a child of it, it (almost
      // certainly) won't exist since we don't track commits which affect
      // it. (Even if it exists, return a consistent result.)
      $this->reason = self::REASON_IS_UNTRACKED_PARENT;
      return array();
    }

    $conn_r = $repository->establishConnection('r');

    $parent_path = DiffusionPathIDQuery::getParentPath($path);
    $path_query = new DiffusionPathIDQuery(
      array(
        $path,
        $parent_path,
      ));
    $path_map = $path_query->loadPathIDs();

    $path_id = $path_map[$path];
    $parent_path_id = $path_map[$parent_path];

    if (empty($path_id)) {
      $this->reason = self::REASON_IS_NONEXISTENT;
      return array();
    }

    if ($commit) {
      $slice_clause = 'AND svnCommit <= '.(int)$commit;
    } else {
      $slice_clause = '';
    }

    $index = queryfx_all(
      $conn_r,
      'SELECT pathID, max(svnCommit) maxCommit FROM %T WHERE
        repositoryID = %d AND parentID = %d
        %Q GROUP BY pathID',
      PhabricatorRepository::TABLE_FILESYSTEM,
      $repository->getID(),
      $path_id,
      $slice_clause);

    if (!$index) {
      if ($path == '/') {
        $this->reason = self::REASON_IS_EMPTY;
      } else {

        // NOTE: The parent path ID is included so this query can take
        // advantage of the table's primary key; it is uniquely determined by
        // the pathID but if we don't do the lookup ourselves MySQL doesn't have
        // the information it needs to avoid a table scan.

        $reasons = queryfx_all(
          $conn_r,
          'SELECT * FROM %T WHERE repositoryID = %d
              AND parentID = %d
              AND pathID = %d
            %Q ORDER BY svnCommit DESC LIMIT 2',
          PhabricatorRepository::TABLE_FILESYSTEM,
          $repository->getID(),
          $parent_path_id,
          $path_id,
          $slice_clause);

        $reason = reset($reasons);

        if (!$reason) {
          $this->reason = self::REASON_IS_NONEXISTENT;
        } else {
          $file_type = $reason['fileType'];
          if (empty($reason['existed'])) {
            $this->reason = self::REASON_IS_DELETED;
            $this->deletedAtCommit = $reason['svnCommit'];
            if (!empty($reasons[1])) {
              $this->existedAtCommit = $reasons[1]['svnCommit'];
            }
          } else if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
            $this->reason = self::REASON_IS_EMPTY;
          } else {
            $this->reason = self::REASON_IS_FILE;
          }
        }
      }
      return array();
    }

    if ($this->shouldOnlyTestValidity()) {
      return true;
    }

    $sql = array();
    foreach ($index as $row) {
      $sql[] =
        '(pathID = '.(int)$row['pathID'].' AND '.
        'svnCommit = '.(int)$row['maxCommit'].')';
    }

    $browse = queryfx_all(
      $conn_r,
      'SELECT *, p.path pathName
        FROM %T f JOIN %T p ON f.pathID = p.id
        WHERE repositoryID = %d
          AND parentID = %d
          AND existed = 1
        AND (%Q)
        ORDER BY pathName',
      PhabricatorRepository::TABLE_FILESYSTEM,
      PhabricatorRepository::TABLE_PATH,
      $repository->getID(),
      $path_id,
      implode(' OR ', $sql));

    $loadable_commits = array();
    foreach ($browse as $key => $file) {
      // We need to strip out directories because we don't store last-modified
      // in the filesystem table.
      if ($file['fileType'] != DifferentialChangeType::FILE_DIRECTORY) {
        $loadable_commits[] = $file['svnCommit'];
        $browse[$key]['hasCommit'] = true;
      }
    }

    $commits = array();
    $commit_data = array();
    if ($loadable_commits) {
      // NOTE: Even though these are integers, use '%Ls' because MySQL doesn't
      // use the second part of the key otherwise!
      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'repositoryID = %d AND commitIdentifier IN (%Ls)',
        $repository->getID(),
        $loadable_commits);
      $commits = mpull($commits, null, 'getCommitIdentifier');
      if ($commits) {
        $commit_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
          'commitID in (%Ld)',
          mpull($commits, 'getID'));
        $commit_data = mpull($commit_data, null, 'getCommitID');
      } else {
        $commit_data = array();
      }
    }

    $path_normal = DiffusionPathIDQuery::normalizePath($path);

    $results = array();
    foreach ($browse as $file) {

      $full_path = $file['pathName'];
      $file_path = ltrim(substr($full_path, strlen($path_normal)), '/');
      $full_path = ltrim($full_path, '/');

      $result = new DiffusionRepositoryPath();
      $result->setPath($file_path);
      $result->setFullPath($full_path);
//      $result->setHash($hash);
      $result->setFileType($file['fileType']);
//      $result->setFileSize($size);

      if (!empty($file['hasCommit'])) {
        $commit = idx($commits, $file['svnCommit']);
        if ($commit) {
          $data = idx($commit_data, $commit->getID());
          $result->setLastModifiedCommit($commit);
          $result->setLastCommitData($data);
        }
      }

      $results[] = $result;
    }

    if (empty($results)) {
      $this->reason = self::REASON_IS_EMPTY;
    }

    return $results;
  }

}
