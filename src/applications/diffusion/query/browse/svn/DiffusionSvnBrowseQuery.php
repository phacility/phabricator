<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class DiffusionSvnBrowseQuery extends DiffusionBrowseQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    $path_normal = '/'.trim($path, '/');

    $conn_r = $repository->establishConnection('r');

    $paths = queryfx_all(
      $conn_r,
      'SELECT id, path FROM %T WHERE path IN (%Ls)',
      PhabricatorRepository::TABLE_PATH,
      array($path_normal));
    $paths = ipull($paths, 'id', 'path');
    $path_id = idx($paths, $path_normal);

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
      if ($this->path == '/') {
        $this->reason = self::REASON_IS_EMPTY;
      } else {
        $reasons = queryfx_all(
          $conn_r,
          'SELECT * FROM %T WHERE repositoryID = %d AND pathID = %d
            %Q ORDER BY svnCommit DESC LIMIT 2',
          PhabricatorRepository::TABLE_FILESYSTEM,
          $repository->getID(),
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

    $sql = array();
    foreach ($index as $row) {
      $sql[] = '('.(int)$row['pathID'].', '.(int)$row['maxCommit'].')';
    }

    $browse = queryfx_all(
      $conn_r,
      'SELECT *, p.path pathName
        FROM %T f JOIN %T p ON f.pathID = p.id
        WHERE repositoryID = %d
          AND parentID = %d
          AND existed = 1
        AND (pathID, svnCommit) in (%Q)
        ORDER BY pathName',
      PhabricatorRepository::TABLE_FILESYSTEM,
      PhabricatorRepository::TABLE_PATH,
      $repository->getID(),
      $path_id,
      implode(', ', $sql));

    $results = array();
    foreach ($browse as $file) {

      $file_path = $file['pathName'];
      $file_path = ltrim(substr($file_path, strlen($path_normal)), '/');

      $result = new DiffusionRepositoryPath();
      $result->setPath($file_path);
//      $result->setHash($hash);
      $result->setFileType($file['fileType']);
//      $result->setFileSize($size);

      $results[] = $result;
    }

    return $results;
  }

}
