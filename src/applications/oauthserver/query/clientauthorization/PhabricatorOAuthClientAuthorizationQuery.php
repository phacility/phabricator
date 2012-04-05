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

final class PhabricatorOAuthClientAuthorizationQuery
extends PhabricatorOffsetPagedQuery {
  private $userPHIDs;

  public function withUserPHIDs(array $phids) {
    $this->userPHIDs = $phids;
    return $this;
  }
  private function getUserPHIDs() {
    return $this->userPHIDs;
  }

  public function execute() {
    $table  = new PhabricatorOAuthClientAuthorization();
    $conn_r = $table->establishConnection('r');

    $where_clause = $this->buildWhereClause($conn_r);
    $limit_clause = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T auth %Q %Q',
      $table->getTableName(),
      $where_clause,
      $limit_clause);

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->getUserPHIDs()) {
      $where[] = qsprintf(
        $conn_r,
        'userPHID IN (%Ls)',
        $this->getUserPHIDs());
    }

    return $this->formatWhereClause($where);
  }
}
