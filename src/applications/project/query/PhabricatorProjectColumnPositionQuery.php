<?php

final class PhabricatorProjectColumnPositionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $boardPHIDs;
  private $objectPHIDs;
  private $columns;

  private $needColumns;
  private $skipImplicitCreate;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withBoardPHIDs(array $board_phids) {
    $this->boardPHIDs = $board_phids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  /**
   * Find objects in specific columns.
   *
   * NOTE: Using this method activates logic which constructs virtual
   * column positions for objects not in any column, if you pass a default
   * column. Normally these results are not returned.
   *
   * @param list<PhabricatorProjectColumn> Columns to look for objects in.
   * @return this
   */
  public function withColumns(array $columns) {
    assert_instances_of($columns, 'PhabricatorProjectColumn');
    $this->columns = $columns;
    return $this;
  }

  public function needColumns($need_columns) {
    $this->needColumns = true;
    return $this;
  }


  /**
   * Skip implicit creation of column positions which are implied but do not
   * yet exist.
   *
   * This is primarily useful internally.
   *
   * @param bool  True to skip implicit creation of column positions.
   * @return this
   */
  public function setSkipImplicitCreate($skip) {
    $this->skipImplicitCreate = $skip;
    return $this;
  }

  // NOTE: For now, boards are always attached to projects. However, they might
  // not be in the future. This generalization just anticipates a future where
  // we let other types of objects (like users) have boards, or let boards
  // contain other types of objects.

  private function newPositionObject() {
    return new PhabricatorProjectColumnPosition();
  }

  private function newColumnQuery() {
    return new PhabricatorProjectColumnQuery();
  }

  private function getBoardMembershipEdgeTypes() {
    return array(
      PhabricatorProjectProjectHasObjectEdgeType::EDGECONST,
    );
  }

  private function getBoardMembershipPHIDTypes() {
    return array(
      ManiphestTaskPHIDType::TYPECONST,
    );
  }

  protected function loadPage() {
    $table = $this->newPositionObject();
    $conn_r = $table->establishConnection('r');

    // We're going to find results by combining two queries: one query finds
    // objects on a board column, while the other query finds objects not on
    // any board column and virtually puts them on the default column.

    $unions = array();

    // First, find all the stuff that's actually on a column.

    $unions[] = qsprintf(
      $conn_r,
      'SELECT * FROM %T %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r));

    // If we have a default column, find all the stuff that's not in any
    // column and put it in the default column.

    $must_type_filter = false;
    if ($this->columns && !$this->skipImplicitCreate) {
      $default_map = array();
      foreach ($this->columns as $column) {
        if ($column->isDefaultColumn()) {
          $default_map[$column->getProjectPHID()] = $column->getPHID();
        }
      }

      if ($default_map) {
        $where = array();

        // Find the edges attached to the boards we have default columns for.

        $where[] = qsprintf(
          $conn_r,
          'e.src IN (%Ls)',
          array_keys($default_map));

        // Find only edges which describe a board relationship.

        $where[] = qsprintf(
          $conn_r,
          'e.type IN (%Ld)',
          $this->getBoardMembershipEdgeTypes());

        if ($this->boardPHIDs !== null) {
          // This should normally be redundant, but construct it anyway if
          // the caller has told us to.
          $where[] = qsprintf(
            $conn_r,
            'e.src IN (%Ls)',
            $this->boardPHIDs);
        }

        if ($this->objectPHIDs !== null) {
          $where[] = qsprintf(
            $conn_r,
            'e.dst IN (%Ls)',
            $this->objectPHIDs);
        }

        $where[] = qsprintf(
          $conn_r,
          'p.id IS NULL');

        $where = $this->formatWhereClause($where);

        $unions[] = qsprintf(
          $conn_r,
          'SELECT NULL id, e.src boardPHID, NULL columnPHID, e.dst objectPHID,
              0 sequence
            FROM %T e LEFT JOIN %T p
              ON e.src = p.boardPHID AND e.dst = p.objectPHID
              %Q',
          PhabricatorEdgeConfig::TABLE_NAME_EDGE,
          $table->getTableName(),
          $where);

        $must_type_filter = true;
      }
    }

    $data = queryfx_all(
      $conn_r,
      '%Q %Q %Q',
      implode(' UNION ALL ', $unions),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    // If we've picked up objects not in any column, we need to filter out any
    // matched objects which have the wrong edge type.
    if ($must_type_filter) {
      $allowed_types = array_fuse($this->getBoardMembershipPHIDTypes());
      foreach ($data as $id => $row) {
        if ($row['columnPHID'] === null) {
          $object_phid = $row['objectPHID'];
          if (empty($allowed_types[phid_get_type($object_phid)])) {
            unset($data[$id]);
          }
        }
      }
    }

    $positions = $table->loadAllFromArray($data);

    // Find the implied positions which don't exist yet. If there are any,
    // we're going to create them.
    $create = array();
    foreach ($positions as $position) {
      if ($position->getColumnPHID() === null) {
        $column_phid = idx($default_map, $position->getBoardPHID());
        $position->setColumnPHID($column_phid);

        $create[] = $position;
      }
    }

    if ($create) {
      // If we're adding several objects to a column, insert the column
      // position objects in object ID order. This means that newly added
      // objects float to the top, and when a group of newly added objects
      // float up at the same time, the most recently created ones end up
      // highest in the list.

      $objects = id(new PhabricatorObjectQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs(mpull($create, 'getObjectPHID'))
        ->execute();
      $objects = mpull($objects, null, 'getPHID');
      $objects = msort($objects, 'getID');

      $create = mgroup($create, 'getObjectPHID');
      $create = array_select_keys($create, array_keys($objects)) + $create;

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

        foreach ($create as $object_phid => $create_positions) {
          foreach ($create_positions as $create_position) {
            $create_position->save();
          }
        }

      unset($unguarded);
    }

    return $positions;
  }

  protected function willFilterPage(array $page) {

    if ($this->needColumns) {
      $column_phids = mpull($page, 'getColumnPHID');
      $columns = $this->newColumnQuery()
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($column_phids)
        ->execute();
      $columns = mpull($columns, null, 'getPHID');

      foreach ($page as $key => $position) {
        $column = idx($columns, $position->getColumnPHID());
        if (!$column) {
          unset($page[$key]);
          continue;
        }

        $position->attachColumn($column);
      }
    }

    return $page;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->boardPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'boardPHID IN (%Ls)',
        $this->boardPHIDs);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->columns !== null) {
      $where[] = qsprintf(
        $conn_r,
        'columnPHID IN (%Ls)',
        mpull($this->columns, 'getPHID'));
    }

    // NOTE: Explicitly not building the paging clause here, since it won't
    // work with the UNION.

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

}
