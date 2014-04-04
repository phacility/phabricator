<?php

final class PhabricatorFeedQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $filterPHIDs;
  private $chronologicalKeys;

  public function setFilterPHIDs(array $phids) {
    $this->filterPHIDs = $phids;
    return $this;
  }

  public function withChronologicalKeys(array $keys) {
    $this->chronologicalKeys = $keys;
    return $this;
  }

  protected function loadPage() {

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

    return $data;
  }

  protected function willFilterPage(array $data) {
    return PhabricatorFeedStory::loadAllFromRows($data, $this->getViewer());
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

    if ($this->chronologicalKeys) {
      // NOTE: We want to use integers in the query so we can take advantage
      // of keys, but can't use %d on 32-bit systems. Make sure all the keys
      // are integers and then format them raw.

      $keys = $this->chronologicalKeys;
      foreach ($keys as $key) {
        if (!ctype_digit($key)) {
          throw new Exception("Key '{$key}' is not a valid chronological key!");
        }
      }

      $where[] = qsprintf(
        $conn_r,
        'ref.chronologicalKey IN (%Q)',
        implode(', ', $keys));
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
    if ($item instanceof PhabricatorFeedStory) {
      return $item->getChronologicalKey();
    }
    return $item['chronologicalKey'];
  }


  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationFeed';
  }

}
