<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class DiffusionPathQuery {

  private $pathIDs;

  public function withPathIDs(array $path_ids) {
    $this->pathIDs = $path_ids;
    return $this;
  }

  public function execute() {
    $conn_r = id(new PhabricatorRepository())->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);

    $results = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q',
      PhabricatorRepository::TABLE_PATH,
      $where);

    return ipull($results, null, 'id');
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->pathIDs) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->pathIDs);
    }

    if ($where) {
      return 'WHERE ('.implode(') AND (', $where).')';
    } else {
      return '';
    }
  }

}
