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

final class PhabricatorFeedQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $filterPHIDs;

  public function setFilterPHIDs(array $phids) {
    $this->filterPHIDs = $phids;
    return $this;
  }

  public function loadPage() {

    $story_table = new PhabricatorFeedStoryData();
    $conn = $story_table->establishConnection('r');

    $data = queryfx_all(
      $conn,
      'SELECT story.* FROM %T story %Q %Q %Q %Q %Q',
      $story_table->getTableName(),
      $this->buildJoinClause($conn),
      $this->buildWhereClause($conn),
      $this->buildGroupClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    $results = PhabricatorFeedStory::loadAllFromRows($data);

    return $this->processResults($results);
  }

  private function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    // NOTE: We perform this join unconditionally (even if we have no filter
    // PHIDs) to omit rows which have no story references. These story data
    // rows are notifications or realtime alerts.

    $ref_table = new PhabricatorFeedStoryReference();
    return qsprintf(
      $conn_r,
      'JOIN %T ref ON ref.chronologicalKey = story.chronologicalKey',
      $ref_table->getTableName());
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->filterPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'ref.objectPHID IN (%Ls)',
        $this->filterPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  private function buildGroupClause(AphrontDatabaseConnection $conn_r) {
    return qsprintf(
      $conn_r,
      'GROUP BY '.($this->filterPHIDs
        ? 'ref.chronologicalKey'
        : 'story.chronologicalKey'));
  }

  protected function getPagingColumn() {
    return ($this->filterPHIDs
      ? 'ref.chronologicalKey'
      : 'story.chronologicalKey');
  }

  protected function getPagingValue($item) {
    return $item->getChronologicalKey();
  }

}
