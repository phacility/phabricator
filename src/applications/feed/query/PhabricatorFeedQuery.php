<?php

final class PhabricatorFeedQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $filterPHIDs;
  private $chronologicalKeys;
  private $rangeMin;
  private $rangeMax;

  public function withFilterPHIDs(array $phids) {
    $this->filterPHIDs = $phids;
    return $this;
  }

  public function withChronologicalKeys(array $keys) {
    $this->chronologicalKeys = $keys;
    return $this;
  }

  public function withEpochInRange($range_min, $range_max) {
    $this->rangeMin = $range_min;
    $this->rangeMax = $range_max;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorFeedStoryData();
  }

  protected function loadPage() {
    // NOTE: We return raw rows from this method, which is a little unusual.
    return $this->loadStandardPageRows($this->newResultObject());
  }

  protected function willFilterPage(array $data) {
    return PhabricatorFeedStory::loadAllFromRows($data, $this->getViewer());
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    // NOTE: We perform this join unconditionally (even if we have no filter
    // PHIDs) to omit rows which have no story references. These story data
    // rows are notifications or realtime alerts.

    $ref_table = new PhabricatorFeedStoryReference();
    $joins[] = qsprintf(
      $conn,
      'JOIN %T ref ON ref.chronologicalKey = story.chronologicalKey',
      $ref_table->getTableName());

    return $joins;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->filterPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'ref.objectPHID IN (%Ls)',
        $this->filterPHIDs);
    }

    if ($this->chronologicalKeys !== null) {
      // NOTE: We want to use integers in the query so we can take advantage
      // of keys, but can't use %d on 32-bit systems. Make sure all the keys
      // are integers and then format them raw.

      $keys = $this->chronologicalKeys;
      foreach ($keys as $key) {
        if (!ctype_digit($key)) {
          throw new Exception(
            pht("Key '%s' is not a valid chronological key!", $key));
        }
      }

      $where[] = qsprintf(
        $conn,
        'ref.chronologicalKey IN (%Q)',
        implode(', ', $keys));
    }

    // NOTE: We may not have 64-bit PHP, so do the shifts in MySQL instead.
    // From EXPLAIN, it appears like MySQL is smart enough to compute the
    // result and make use of keys to execute the query.

    if ($this->rangeMin !== null) {
      $where[] = qsprintf(
        $conn,
        'ref.chronologicalKey >= (%d << 32)',
        $this->rangeMin);
    }

    if ($this->rangeMax !== null) {
      $where[] = qsprintf(
        $conn,
        'ref.chronologicalKey < (%d << 32)',
        $this->rangeMax);
    }

    return $where;
  }

  protected function buildGroupClause(AphrontDatabaseConnection $conn) {
    if ($this->filterPHIDs !== null) {
      return qsprintf($conn, 'GROUP BY ref.chronologicalKey');
    } else {
      return qsprintf($conn, 'GROUP BY story.chronologicalKey');
    }
  }

  protected function getDefaultOrderVector() {
    return array('key');
  }

  public function getBuiltinOrders() {
    return array(
      'newest' => array(
        'vector' => array('key'),
        'name' => pht('Creation (Newest First)'),
        'aliases' => array('created'),
      ),
      'oldest' => array(
        'vector' => array('-key'),
        'name' => pht('Creation (Oldest First)'),
      ),
    );
  }

  public function getOrderableColumns() {
    $table = ($this->filterPHIDs ? 'ref' : 'story');
    return array(
      'key' => array(
        'table' => $table,
        'column' => 'chronologicalKey',
        'type' => 'string',
        'unique' => true,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    return array(
      'key' => $cursor,
    );
  }

  protected function getResultCursor($item) {
    if ($item instanceof PhabricatorFeedStory) {
      return $item->getChronologicalKey();
    }
    return $item['chronologicalKey'];
  }

  protected function getPrimaryTableAlias() {
    return 'story';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFeedApplication';
  }

}
