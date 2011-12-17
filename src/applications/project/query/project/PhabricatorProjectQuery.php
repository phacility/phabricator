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

final class PhabricatorProjectQuery {

  private $owners;
  private $members;

  private $limit;
  private $offset;

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function setOwners(array $owners) {
    $this->owners = $owners;
    return $this;
  }

  public function setMembers(array $members) {
    $this->members = $members;
    return $this;
  }

  public function execute() {
    $table = id(new PhabricatorProject());
    $conn_r = $table->establishConnection('r');

    $joins = $this->buildJoinsClause($conn_r);

    $limit = null;
    if ($this->limit) {
      $limit = qsprintf(
        $conn_r,
        'LIMIT %d, %d',
        $this->offset,
        $this->limit);
    } else if ($this->offset) {
      $limit = qsprintf(
        $conn_r,
        'LIMIT %d, %d',
        $this->offset,
        PHP_INT_MAX);
    }

    $data = queryfx_all(
      $conn_r,
      'SELECT p.* FROM %T p %Q %Q',
      $table->getTableName(),
      $joins,
      $limit);

    return $table->loadAllFromArray($data);
  }

  private function buildJoinsClause($conn_r) {
    $affil_table = new PhabricatorProjectAffiliation();

    $joins = array();
    if ($this->owners) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T owner ON owner.projectPHID = p.phid AND owner.isOwner = 1
          AND owner.userPHID in (%Ls)',
        $affil_table->getTableName(),
        $this->owners);
    }

    if ($this->members) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T member ON member.projectPHID = p.phid
          AND member.userPHID in (%Ls)',
        $affil_table->getTableName(),
        $this->members);
    }

    return implode(' ', $joins);
  }

}
