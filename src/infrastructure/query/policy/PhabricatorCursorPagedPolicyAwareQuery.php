<?php

/**
 * A query class which uses cursor-based paging. This paging is much more
 * performant than offset-based paging in the presence of policy filtering.
 *
 * @task appsearch Integration with ApplicationSearch
 * @task paging Paging
 * @task order Result Ordering
 */
abstract class PhabricatorCursorPagedPolicyAwareQuery
  extends PhabricatorPolicyAwareQuery {

  private $afterID;
  private $beforeID;
  private $applicationSearchConstraints = array();
  private $applicationSearchOrders = array();
  private $internalPaging;
  private $orderVector;

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
   * Return the alias this query uses to identify the primary table.
   *
   * Some automatic query constructions may need to be qualified with a table
   * alias if the query performs joins which make column names ambiguous. If
   * this is the case, return the alias for the primary table the query
   * uses; generally the object table which has `id` and `phid` columns.
   *
   * @return string Alias for the primary table.
   */
  protected function getPrimaryTableAlias() {
    return null;
  }


/* -(  Paging  )------------------------------------------------------------- */


  protected function buildPagingClause(AphrontDatabaseConnection $conn) {
    $orderable = $this->getOrderableColumns();

    // TODO: Remove this once subqueries modernize.
    if (!$orderable) {
      if ($this->beforeID) {
        return qsprintf(
          $conn,
          '%Q %Q %s',
          $this->getPagingColumn(),
          $this->getReversePaging() ? '<' : '>',
          $this->beforeID);
      } else if ($this->afterID) {
        return qsprintf(
          $conn,
          '%Q %Q %s',
          $this->getPagingColumn(),
          $this->getReversePaging() ? '>' : '<',
          $this->afterID);
      } else {
        return null;
      }
    }

    $vector = $this->getOrderVector();

    if ($this->beforeID !== null) {
      $cursor = $this->beforeID;
      $reversed = true;
    } else if ($this->afterID !== null) {
      $cursor = $this->afterID;
      $reversed = false;
    } else {
      // No paging is being applied to this query so we do not need to
      // construct a paging clause.
      return '';
    }

    $keys = array();
    foreach ($vector as $order) {
      $keys[] = $order->getOrderKey();
    }

    $value_map = $this->getPagingValueMap($cursor, $keys);

    $columns = array();
    foreach ($vector as $order) {
      $key = $order->getOrderKey();

      if (!array_key_exists($key, $value_map)) {
        throw new Exception(
          pht(
            'Query "%s" failed to return a value from getPagingValueMap() '.
            'for column "%s".',
            get_class($this),
            $key));
      }

      $column = $orderable[$key];
      $column['value'] = $value_map[$key];

      $columns[] = $column;
    }

    return $this->buildPagingClauseFromMultipleColumns(
      $conn,
      $columns,
      array(
        'reversed' => $reversed,
      ));
  }

  protected function getPagingValueMap($cursor, array $keys) {
    // TODO: This is a hack to make this work with existing classes for now.
    return array(
      'id' => $cursor,
    );
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
   *         'table' => 't',
   *         'column' => 'title',
   *         'type' => 'string',
   *         'value' => $cursor->getTitle(),
   *         'reverse' => true,
   *       ),
   *       array(
   *         'table' => 't',
   *         'column' => 'id',
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
          'table' => 'optional string|null',
          'column' => 'string',
          'value' => 'wild',
          'type' => 'string',
          'reverse' => 'optional bool',
          'unique' => 'optional bool',
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
      $type = $column['type'];
      switch ($type) {
        case 'null':
          $value = qsprintf($conn, '%d', ($column['value'] ? 0 : 1));
          break;
        case 'int':
          $value = qsprintf($conn, '%d', $column['value']);
          break;
        case 'float':
          $value = qsprintf($conn, '%f', $column['value']);
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

      $table_name = idx($column, 'table');
      $column_name = $column['column'];
      if ($table_name !== null) {
        $field = qsprintf($conn, '%T.%T', $table_name, $column_name);
      } else {
        $field = qsprintf($conn, '%T', $column_name);
      }

      if ($type == 'null') {
        $field = qsprintf($conn, '(%Q IS NULL)', $field);
      }

      $clause[] = qsprintf(
        $conn,
        '%Q %Q %Q',
        $field,
        $reverse ? '>' : '<',
        $value);
      $clauses[] = '('.implode(') AND (', $clause).')';

      $accumulated[] = qsprintf(
        $conn,
        '%Q = %Q',
        $field,
        $value);
    }

    return '('.implode(') OR (', $clauses).')';
  }


/* -(  Result Ordering  )---------------------------------------------------- */


  /**
   * @task order
   */
  public function setOrderVector($vector) {
    $vector = PhabricatorQueryOrderVector::newFromVector($vector);

    $orderable = $this->getOrderableColumns();

    // Make sure that all the components identify valid columns.
    $unique = array();
    foreach ($vector as $order) {
      $key = $order->getOrderKey();
      if (empty($orderable[$key])) {
        $valid = implode(', ', array_keys($orderable));
        throw new Exception(
          pht(
            'This query ("%s") does not support sorting by order key "%s". '.
            'Supported orders are: %s.',
            get_class($this),
            $key,
            $valid));
      }

      $unique[$key] = idx($orderable[$key], 'unique', false);
    }

    // Make sure that the last column is unique so that this is a strong
    // ordering which can be used for paging.
    $last = last($unique);
    if ($last !== true) {
      throw new Exception(
        pht(
          'Order vector "%s" is invalid: the last column in an order must '.
          'be a column with unique values, but "%s" is not unique.',
          $vector->getAsString(),
          last_key($unique)));
    }

    // Make sure that other columns are not unique; an ordering like "id, name"
    // does not make sense because only "id" can ever have an effect.
    array_pop($unique);
    foreach ($unique as $key => $is_unique) {
      if ($is_unique) {
        throw new Exception(
          pht(
            'Order vector "%s" is invalid: only the last column in an order '.
            'may be unique, but "%s" is a unique column and not the last '.
            'column in the order.',
            $vector->getAsString(),
            $key));
      }
    }

    $this->orderVector = $vector;
    return $this;
  }


  /**
   * @task order
   */
  private function getOrderVector() {
    if (!$this->orderVector) {
      $vector = $this->getDefaultOrderVector();
      $vector = PhabricatorQueryOrderVector::newFromVector($vector);

      // We call setOrderVector() here to apply checks to the default vector.
      // This catches any errors in the implementation.
      $this->setOrderVector($vector);
    }

    return $this->orderVector;
  }


  /**
   * @task order
   */
  protected function getDefaultOrderVector() {
    return array('id');
  }


  /**
   * @task order
   */
  public function getOrderableColumns() {
    // TODO: Remove this once all subclasses move off the old stuff.
    if ($this->getPagingColumn() !== 'id') {
      // This class has bad old custom logic around paging, so return nothing
      // here. This deactivates the new order code.
      return array();
    }

    return array(
      'id' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'id',
        'reverse' => false,
        'type' => 'int',
        'unique' => true,
      ),
    );
  }


  /**
   * @task order
   */
  final protected function buildOrderClause(AphrontDatabaseConnection $conn) {
    $orderable = $this->getOrderableColumns();

    // TODO: Remove this once all subclasses move off the old stuff. We'll
    // only enter this block for code using older ordering mechanisms. New
    // code should expose an orderable column list.
    if (!$orderable) {
      if ($this->beforeID) {
        return qsprintf(
          $conn,
          'ORDER BY %Q %Q',
          $this->getPagingColumn(),
          $this->getReversePaging() ? 'DESC' : 'ASC');
      } else {
        return qsprintf(
          $conn,
          'ORDER BY %Q %Q',
          $this->getPagingColumn(),
          $this->getReversePaging() ? 'ASC' : 'DESC');
      }
    }

    $vector = $this->getOrderVector();

    $parts = array();
    foreach ($vector as $order) {
      $part = $orderable[$order->getOrderKey()];
      if ($order->getIsReversed()) {
        $part['reverse'] = !idx($part, 'reverse', false);
      }
      $parts[] = $part;
    }

    return $this->formatOrderClause($conn, $parts);
  }


  /**
   * @task order
   */
  protected function formatOrderClause(
    AphrontDatabaseConnection $conn,
    array $parts) {

    $is_query_reversed = false;
    if ($this->getReversePaging()) {
      $is_query_reversed = !$is_query_reversed;
    }

    if ($this->getBeforeID()) {
      $is_query_reversed = !$is_query_reversed;
    }

    $sql = array();
    foreach ($parts as $key => $part) {
      $is_column_reversed = !empty($part['reverse']);

      $descending = true;
      if ($is_query_reversed) {
        $descending = !$descending;
      }

      if ($is_column_reversed) {
        $descending = !$descending;
      }

      $table = idx($part, 'table');
      $column = $part['column'];

      if ($descending) {
        if ($table !== null) {
          $sql[] = qsprintf($conn, '%T.%T DESC', $table, $column);
        } else {
          $sql[] = qsprintf($conn, '%T DESC', $column);
        }
      } else {
        if ($table !== null) {
          $sql[] = qsprintf($conn, '%T.%T ASC', $table, $column);
        } else {
          $sql[] = qsprintf($conn, '%T ASC', $column);
        }
      }
    }

    return qsprintf($conn, 'ORDER BY %Q', implode(', ', $sql));
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
   * Order the results by an ApplicationSearch index.
   *
   * @param PhabricatorCustomField Field to which the index belongs.
   * @param PhabricatorCustomFieldIndexStorage Table where the index is stored.
   * @param bool True to sort ascending.
   * @return this
   * @task appsearch
   */
  public function withApplicationSearchOrder(
    PhabricatorCustomField $field,
    PhabricatorCustomFieldIndexStorage $index,
    $ascending) {

    $this->applicationSearchOrders[] = array(
      'key' => $field->getFieldKey(),
      'type' => $index->getIndexValueType(),
      'table' => $index->getTableName(),
      'index' => $index->getIndexKey(),
      'ascending' => $ascending,
    );

    return $this;
  }


  /**
   * Get the name of the query's primary object PHID column, for constructing
   * JOIN clauses. Normally (and by default) this is just `"phid"`, but it may
   * be something more exotic.
   *
   * See @{method:getPrimaryTableAlias} if the column needs to be qualified with
   * a table alias.
   *
   * @return string Column name.
   * @task appsearch
   */
  protected function getApplicationSearchObjectPHIDColumn() {
    if ($this->getPrimaryTableAlias()) {
      $prefix = $this->getPrimaryTableAlias().'.';
    } else {
      $prefix = '';
    }

    return $prefix.'phid';
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

    foreach ($this->applicationSearchOrders as $key => $order) {
      $table = $order['table'];
      $alias = 'appsearch_order_'.$key;
      $index = $order['index'];
      $phid_column = $this->getApplicationSearchObjectPHIDColumn();

      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T %T ON %T.objectPHID = %Q
          AND %T.indexKey = %s',
        $table,
        $alias,
        $alias,
        $phid_column,
        $alias,
        $index);
    }

    return implode(' ', $joins);
  }

  protected function buildApplicationSearchOrders(
    AphrontDatabaseConnection $conn_r,
    $reverse) {

    $orders = array();
    foreach ($this->applicationSearchOrders as $key => $order) {
      $alias = 'appsearch_order_'.$key;

      if ($order['ascending'] xor $reverse) {
        $orders[] = qsprintf($conn_r, '%T.indexValue ASC', $alias);
      } else {
        $orders[] = qsprintf($conn_r, '%T.indexValue DESC', $alias);
      }
    }

    return $orders;
  }

  protected function buildApplicationSearchPagination(
    AphrontDatabaseConnection $conn_r,
    $cursor) {

    // We have to get the current field values on the cursor object.
    $fields = PhabricatorCustomField::getObjectFields(
      $cursor,
      PhabricatorCustomField::ROLE_APPLICATIONSEARCH);
    $fields->setViewer($this->getViewer());
    $fields->readFieldsFromStorage($cursor);

    $fields = mpull($fields->getFields(), null, 'getFieldKey');

    $columns = array();
    foreach ($this->applicationSearchOrders as $key => $order) {
      $alias = 'appsearch_order_'.$key;

      $field = idx($fields, $order['key']);

      $columns[] = array(
        'name' => $alias.'.indexValue',
        'value' => $field->getValueForStorage(),
        'type' => $order['type'],
      );
    }

    return $columns;
  }

}
