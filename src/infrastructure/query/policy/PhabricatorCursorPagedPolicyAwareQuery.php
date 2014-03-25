<?php

/**
 * A query class which uses cursor-based paging. This paging is much more
 * performant than offset-based paging in the presence of policy filtering.
 *
 * @task appsearch Integration with ApplicationSearch
 */
abstract class PhabricatorCursorPagedPolicyAwareQuery
  extends PhabricatorPolicyAwareQuery {

  private $afterID;
  private $beforeID;
  private $applicationSearchConstraints = array();
  private $internalPaging;

  protected function getPagingColumn() {
    return 'id';
  }

  protected function getPagingValue($result) {
    if (!is_object($result)) {
      // This interface can't be typehinted and PHP gets really angry if we
      // call a method on a non-object, so add an explicit check here.
      throw new Exception(pht('Expected object, got "%s"!', gettype($result)));
    }
    return $result->getID();
  }

  protected function getReversePaging() {
    return false;
  }

  protected function nextPage(array $page) {
    // See getPagingViewer() for a description of this flag.
    $this->internalPaging = true;

    if ($this->beforeID) {
      $this->beforeID = $this->getPagingValue(last($page));
    } else {
      $this->afterID = $this->getPagingValue(last($page));
    }
  }

  final public function setAfterID($object_id) {
    $this->afterID = $object_id;
    return $this;
  }

  final protected function getAfterID() {
    return $this->afterID;
  }

  final public function setBeforeID($object_id) {
    $this->beforeID = $object_id;
    return $this;
  }

  final protected function getBeforeID() {
    return $this->beforeID;
  }


  /**
   * Get the viewer for making cursor paging queries.
   *
   * NOTE: You should ONLY use this viewer to load cursor objects while
   * building paging queries.
   *
   * Cursor paging can happen in two ways. First, the user can request a page
   * like `/stuff/?after=33`, which explicitly causes paging. Otherwise, we
   * can fall back to implicit paging if we filter some results out of a
   * result list because the user can't see them and need to go fetch some more
   * results to generate a large enough result list.
   *
   * In the first case, want to use the viewer's policies to load the object.
   * This prevents an attacker from figuring out information about an object
   * they can't see by executing queries like `/stuff/?after=33&order=name`,
   * which would otherwise give them a hint about the name of the object.
   * Generally, if a user can't see an object, they can't use it to page.
   *
   * In the second case, we need to load the object whether the user can see
   * it or not, because we need to examine new results. For example, if a user
   * loads `/stuff/` and we run a query for the first 100 items that they can
   * see, but the first 100 rows in the database aren't visible, we need to
   * be able to issue a query for the next 100 results. If we can't load the
   * cursor object, we'll fail or issue the same query over and over again.
   * So, generally, internal paging must bypass policy controls.
   *
   * This method returns the appropriate viewer, based on the context in which
   * the paging is occuring.
   *
   * @return PhabricatorUser Viewer for executing paging queries.
   */
  final protected function getPagingViewer() {
    if ($this->internalPaging) {
      return PhabricatorUser::getOmnipotentUser();
    } else {
      return $this->getViewer();
    }
  }

  final protected function buildLimitClause(AphrontDatabaseConnection $conn_r) {
    if ($this->getRawResultLimit()) {
      return qsprintf($conn_r, 'LIMIT %d', $this->getRawResultLimit());
    } else {
      return '';
    }
  }

  protected function buildPagingClause(
    AphrontDatabaseConnection $conn_r) {

    if ($this->beforeID) {
      return qsprintf(
        $conn_r,
        '%Q %Q %s',
        $this->getPagingColumn(),
        $this->getReversePaging() ? '<' : '>',
        $this->beforeID);
    } else if ($this->afterID) {
      return qsprintf(
        $conn_r,
        '%Q %Q %s',
        $this->getPagingColumn(),
        $this->getReversePaging() ? '>' : '<',
        $this->afterID);
    }

    return null;
  }

  final protected function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    if ($this->beforeID) {
      return qsprintf(
        $conn_r,
        'ORDER BY %Q %Q',
        $this->getPagingColumn(),
        $this->getReversePaging() ? 'DESC' : 'ASC');
    } else {
      return qsprintf(
        $conn_r,
        'ORDER BY %Q %Q',
        $this->getPagingColumn(),
        $this->getReversePaging() ? 'ASC' : 'DESC');
    }
  }

  final protected function didLoadResults(array $results) {
    if ($this->beforeID) {
      $results = array_reverse($results, $preserve_keys = true);
    }
    return $results;
  }

  final public function executeWithCursorPager(AphrontCursorPagerView $pager) {
    $this->setLimit($pager->getPageSize() + 1);

    if ($pager->getAfterID()) {
      $this->setAfterID($pager->getAfterID());
    } else if ($pager->getBeforeID()) {
      $this->setBeforeID($pager->getBeforeID());
    }

    $results = $this->execute();

    $sliced_results = $pager->sliceResults($results);

    if ($sliced_results) {
      if ($pager->getBeforeID() || (count($results) > $pager->getPageSize())) {
        $pager->setNextPageID($this->getPagingValue(last($sliced_results)));
      }

      if ($pager->getAfterID() ||
         ($pager->getBeforeID() && (count($results) > $pager->getPageSize()))) {
        $pager->setPrevPageID($this->getPagingValue(head($sliced_results)));
      }
    }

    return $sliced_results;
  }


  /**
   * Simplifies the task of constructing a paging clause across multiple
   * columns. In the general case, this looks like:
   *
   *   A > a OR (A = a AND B > b) OR (A = a AND B = b AND C > c)
   *
   * To build a clause, specify the name, type, and value of each column
   * to include:
   *
   *   $this->buildPagingClauseFromMultipleColumns(
   *     $conn_r,
   *     array(
   *       array(
   *         'name' => 'title',
   *         'type' => 'string',
   *         'value' => $cursor->getTitle(),
   *         'reverse' => true,
   *       ),
   *       array(
   *         'name' => 'id',
   *         'type' => 'int',
   *         'value' => $cursor->getID(),
   *       ),
   *     ),
   *     array(
   *       'reversed' => $is_reversed,
   *     ));
   *
   * This method will then return a composable clause for inclusion in WHERE.
   *
   * @param AphrontDatabaseConnection Connection query will execute on.
   * @param list<map> Column description dictionaries.
   * @param map Additional constuction options.
   * @return string Query clause.
   */
  final protected function buildPagingClauseFromMultipleColumns(
    AphrontDatabaseConnection $conn,
    array $columns,
    array $options) {

    foreach ($columns as $column) {
      PhutilTypeSpec::checkMap(
        $column,
        array(
          'name' => 'string',
          'value' => 'wild',
          'type' => 'string',
          'reverse' => 'optional bool',
        ));
    }

    PhutilTypeSpec::checkMap(
      $options,
      array(
        'reversed' => 'optional bool',
      ));

    $is_query_reversed = idx($options, 'reversed', false);

    $clauses = array();
    $accumulated = array();
    $last_key = last_key($columns);
    foreach ($columns as $key => $column) {
      $name = $column['name'];

      $type = $column['type'];
      switch ($type) {
        case 'int':
          $value = qsprintf($conn, '%d', $column['value']);
          break;
        case 'string':
          $value = qsprintf($conn, '%s', $column['value']);
          break;
        default:
          throw new Exception("Unknown column type '{$type}'!");
      }

      $is_column_reversed = idx($column, 'reverse', false);
      $reverse = ($is_query_reversed xor $is_column_reversed);

      $clause = $accumulated;
      $clause[] = qsprintf(
        $conn,
        '%Q %Q %Q',
        $name,
        $reverse ? '>' : '<',
        $value);
      $clauses[] = '('.implode(') AND (', $clause).')';

      $accumulated[] = qsprintf(
        $conn,
        '%Q = %Q',
        $name,
        $value);
    }

    return '('.implode(') OR (', $clauses).')';
  }


/* -(  Application Search  )------------------------------------------------- */


  /**
   * Constrain the query with an ApplicationSearch index, requiring field values
   * contain at least one of the values in a set.
   *
   * This constraint can build the most common types of queries, like:
   *
   *   - Find users with shirt sizes "X" or "XL".
   *   - Find shoes with size "13".
   *
   * @param PhabricatorCustomFieldIndexStorage Table where the index is stored.
   * @param string|list<string> One or more values to filter by.
   * @return this
   * @task appsearch
   */
  public function withApplicationSearchContainsConstraint(
    PhabricatorCustomFieldIndexStorage $index,
    $value) {

    $this->applicationSearchConstraints[] = array(
      'type'  => $index->getIndexValueType(),
      'cond'  => '=',
      'table' => $index->getTableName(),
      'index' => $index->getIndexKey(),
      'value' => $value,
    );

    return $this;
  }


  /**
   * Constrain the query with an ApplicationSearch index, requiring values
   * exist in a given range.
   *
   * This constraint is useful for expressing date ranges:
   *
   *   - Find events between July 1st and July 7th.
   *
   * The ends of the range are inclusive, so a `$min` of `3` and a `$max` of
   * `5` will match fields with values `3`, `4`, or `5`. Providing `null` for
   * either end of the range will leave that end of the constraint open.
   *
   * @param PhabricatorCustomFieldIndexStorage Table where the index is stored.
   * @param int|null Minimum permissible value, inclusive.
   * @param int|null Maximum permissible value, inclusive.
   * @return this
   * @task appsearch
   */
  public function withApplicationSearchRangeConstraint(
    PhabricatorCustomFieldIndexStorage $index,
    $min,
    $max) {

    $index_type = $index->getIndexValueType();
    if ($index_type != 'int') {
      throw new Exception(
        pht(
          'Attempting to apply a range constraint to a field with index type '.
          '"%s", expected type "%s".',
          $index_type,
          'int'));
    }

    $this->applicationSearchConstraints[] = array(
      'type' => $index->getIndexValueType(),
      'cond' => 'range',
      'table' => $index->getTableName(),
      'index' => $index->getIndexKey(),
      'value' => array($min, $max),
    );

    return $this;
  }


  /**
   * Get the name of the query's primary object PHID column, for constructing
   * JOIN clauses. Normally (and by default) this is just `"phid"`, but if the
   * query construction requires a table alias it may be something like
   * `"task.phid"`.
   *
   * @return string Column name.
   * @task appsearch
   */
  protected function getApplicationSearchObjectPHIDColumn() {
    return 'phid';
  }


  /**
   * Determine if the JOINs built by ApplicationSearch might cause each primary
   * object to return multiple result rows. Generally, this means the query
   * needs an extra GROUP BY clause.
   *
   * @return bool True if the query may return multiple rows for each object.
   * @task appsearch
   */
  protected function getApplicationSearchMayJoinMultipleRows() {
    foreach ($this->applicationSearchConstraints as $constraint) {
      $type = $constraint['type'];
      $value = $constraint['value'];
      $cond = $constraint['cond'];

      switch ($cond) {
        case '=':
          switch ($type) {
            case 'string':
            case 'int':
              if (count((array)$value) > 1) {
                return true;
              }
              break;
            default:
              throw new Exception(pht('Unknown index type "%s"!', $type));
          }
          break;
        case 'range':
          // NOTE: It's possible to write a custom field where multiple rows
          // match a range constraint, but we don't currently ship any in the
          // upstream and I can't immediately come up with cases where this
          // would make sense.
          break;
        default:
          throw new Exception(pht('Unknown constraint condition "%s"!', $cond));
      }
    }

    return false;
  }


  /**
   * Construct a GROUP BY clause appropriate for ApplicationSearch constraints.
   *
   * @param AphrontDatabaseConnection Connection executing the query.
   * @return string Group clause.
   * @task appsearch
   */
  protected function buildApplicationSearchGroupClause(
    AphrontDatabaseConnection $conn_r) {

    if ($this->getApplicationSearchMayJoinMultipleRows()) {
      return qsprintf(
        $conn_r,
        'GROUP BY %Q',
        $this->getApplicationSearchObjectPHIDColumn());
    } else {
      return '';
    }
  }


  /**
   * Construct a JOIN clause appropriate for applying ApplicationSearch
   * constraints.
   *
   * @param AphrontDatabaseConnection Connection executing the query.
   * @return string Join clause.
   * @task appsearch
   */
  protected function buildApplicationSearchJoinClause(
    AphrontDatabaseConnection $conn_r) {

    $joins = array();
    foreach ($this->applicationSearchConstraints as $key => $constraint) {
      $table = $constraint['table'];
      $alias = 'appsearch_'.$key;
      $index = $constraint['index'];
      $cond = $constraint['cond'];
      $phid_column = $this->getApplicationSearchObjectPHIDColumn();
      switch ($cond) {
        case '=':
          $type = $constraint['type'];
          switch ($type) {
            case 'string':
              $constraint_clause = qsprintf(
                $conn_r,
                '%T.indexValue IN (%Ls)',
                $alias,
                (array)$constraint['value']);
              break;
            case 'int':
              $constraint_clause = qsprintf(
                $conn_r,
                '%T.indexValue IN (%Ld)',
                $alias,
                (array)$constraint['value']);
              break;
            default:
              throw new Exception(pht('Unknown index type "%s"!', $type));
          }

          $joins[] = qsprintf(
            $conn_r,
            'JOIN %T %T ON %T.objectPHID = %Q
              AND %T.indexKey = %s
              AND (%Q)',
            $table,
            $alias,
            $alias,
            $phid_column,
            $alias,
            $index,
            $constraint_clause);
          break;
        case 'range':
          list($min, $max) = $constraint['value'];
          if (($min === null) && ($max === null)) {
            // If there's no actual range constraint, just move on.
            break;
          }

          if ($min === null) {
            $constraint_clause = qsprintf(
              $conn_r,
              '%T.indexValue <= %d',
              $alias,
              $max);
          } else if ($max === null) {
            $constraint_clause = qsprintf(
              $conn_r,
              '%T.indexValue >= %d',
              $alias,
              $min);
          } else {
            $constraint_clause = qsprintf(
              $conn_r,
              '%T.indexValue BETWEEN %d AND %d',
              $alias,
              $min,
              $max);
          }

          $joins[] = qsprintf(
            $conn_r,
            'JOIN %T %T ON %T.objectPHID = %Q
              AND %T.indexKey = %s
              AND (%Q)',
            $table,
            $alias,
            $alias,
            $phid_column,
            $alias,
            $index,
            $constraint_clause);
          break;
        default:
          throw new Exception(pht('Unknown constraint condition "%s"!', $cond));
      }
    }

    return implode(' ', $joins);
  }

}
