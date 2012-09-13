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

final class PhabricatorOwnersPackageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ownerPHIDs;

  /**
   * Owners are direct owners, and members of owning projects.
   */
  public function withOwnerPHIDs(array $phids) {
    $this->ownerPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorOwnersPackage();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT p.* FROM %T p %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  private function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->ownerPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T o ON o.packageID = p.id',
        id(new PhabricatorOwnersOwner())->getTableName());
    }

    return implode('', $joins);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ownerPHIDs) {
      $base_phids = $this->ownerPHIDs;

      $query = new PhabricatorProjectQuery();
      $query->setViewer($this->getViewer());
      $query->withMemberPHIDs($base_phids);
      $projects = $query->execute();
      $project_phids = mpull($projects, 'getPHID');

      $all_phids = array_merge($base_phids, $project_phids);

      $where[] = qsprintf(
        $conn_r,
        'o.userPHID IN (%Ls)',
        $all_phids);
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

}
