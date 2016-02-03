<?php

final class PhabricatorBoardLayoutEngine extends Phobject {

  private $viewer;
  private $boardPHIDs;
  private $objectPHIDs;
  private $boards;
  private $columnMap = array();
  private $objectColumnMap = array();
  private $boardLayout = array();

  private $remQueue = array();
  private $addQueue = array();

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setBoardPHIDs(array $board_phids) {
    $this->boardPHIDs = $board_phids;
    return $this;
  }

  public function getBoardPHIDs() {
    return $this->boardPHIDs;
  }

  public function setObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function getObjectPHIDs() {
    return $this->objectPHIDs;
  }

  public function executeLayout() {
    $viewer = $this->getViewer();

    $boards = $this->loadBoards();
    if (!$boards) {
      return $this;
    }

    $columns = $this->loadColumns($boards);
    $positions = $this->loadPositions($boards);

    foreach ($boards as $board_phid => $board) {
      $board_columns = idx($columns, $board_phid);

      // Don't layout boards with no columns. These boards need to be formally
      // created first.
      if (!$columns) {
        continue;
      }

      $board_positions = idx($positions, $board_phid, array());

      $this->layoutBoard($board, $board_columns, $board_positions);
    }

    return $this;
  }

  public function getColumns($board_phid) {
    $columns = idx($this->boardLayout, $board_phid, array());
    return array_select_keys($this->columnMap, array_keys($columns));
  }

  public function getColumnObjectPHIDs($board_phid, $column_phid) {
    $columns = idx($this->boardLayout, $board_phid, array());
    $positions = idx($columns, $column_phid, array());
    return mpull($positions, 'getObjectPHID');
  }

  public function getObjectColumns($board_phid, $object_phid) {
    $board_map = idx($this->objectColumnMap, $board_phid, array());

    $column_phids = idx($board_map, $object_phid);
    if (!$column_phids) {
      return array();
    }

    return array_select_keys($this->columnMap, $column_phids);
  }

  public function queueRemovePosition(
    $board_phid,
    $column_phid,
    $object_phid) {

    $board_layout = idx($this->boardLayout, $board_phid, array());
    $positions = idx($board_layout, $column_phid, array());
    $position = idx($positions, $object_phid);

    if ($position) {
      $this->remQueue[] = $position;

      // If this position hasn't been saved yet, get it out of the add queue.
      if (!$position->getID()) {
        foreach ($this->addQueue as $key => $add_position) {
          if ($add_position === $position) {
            unset($this->addQueue[$key]);
          }
        }
      }
    }

    unset($this->boardLayout[$board_phid][$column_phid][$object_phid]);

    return $this;
  }

  public function queueAddPositionBefore(
    $board_phid,
    $column_phid,
    $object_phid,
    $before_phid) {

    return $this->queueAddPositionRelative(
      $board_phid,
      $column_phid,
      $object_phid,
      $before_phid,
      true);
  }

  public function queueAddPositionAfter(
    $board_phid,
    $column_phid,
    $object_phid,
    $after_phid) {

    return $this->queueAddPositionRelative(
      $board_phid,
      $column_phid,
      $object_phid,
      $after_phid,
      false);
  }

  public function queueAddPosition(
    $board_phid,
    $column_phid,
    $object_phid) {
    return $this->queueAddPositionRelative(
      $board_phid,
      $column_phid,
      $object_phid,
      null,
      true);
  }

  private function queueAddPositionRelative(
    $board_phid,
    $column_phid,
    $object_phid,
    $relative_phid,
    $is_before) {

    $board_layout = idx($this->boardLayout, $board_phid, array());
    $positions = idx($board_layout, $column_phid, array());

    // Check if the object is already in the column, and remove it if it is.
    $object_position = idx($positions, $object_phid);
    unset($positions[$object_phid]);

    if (!$object_position) {
      $object_position = id(new PhabricatorProjectColumnPosition())
        ->setBoardPHID($board_phid)
        ->setColumnPHID($column_phid)
        ->setObjectPHID($object_phid);
    }

    $found = false;
    if (!$positions) {
      $object_position->setSequence(0);
    } else {
      foreach ($positions as $position) {
        if (!$found) {
          if ($relative_phid === null) {
            $is_match = true;
          } else {
            $position_phid = $position->getObjectPHID();
            $is_match = ($relative_phid == $position_phid);
          }

          if ($is_match) {
            $found = true;

            $sequence = $position->getSequence();

            if (!$is_before) {
              $sequence++;
            }

            $object_position->setSequence($sequence++);

            if (!$is_before) {
              // If we're inserting after this position, continue the loop so
              // we don't update it.
              continue;
            }
          }
        }

        if ($found) {
          $position->setSequence($sequence++);
          $this->addQueue[] = $position;
        }
      }
    }

    if ($relative_phid && !$found) {
      throw new Exception(
        pht(
          'Unable to find object "%s" in column "%s" on board "%s".',
          $relative_phid,
          $column_phid,
          $board_phid));
    }

    $this->addQueue[] = $object_position;

    $positions[$object_phid] = $object_position;
    $positions = msort($positions, 'getOrderingKey');

    $this->boardLayout[$board_phid][$column_phid] = $positions;

    return $this;
  }

  public function applyPositionUpdates() {
    foreach ($this->remQueue as $position) {
      if ($position->getID()) {
        $position->delete();
      }
    }
    $this->remQueue = array();

    $adds = array();
    $updates = array();

    foreach ($this->addQueue as $position) {
      $id = $position->getID();
      if ($id) {
        $updates[$id] = $position;
      } else {
        $adds[] = $position;
      }
    }
    $this->addQueue = array();

    $table = new PhabricatorProjectColumnPosition();
    $conn_w = $table->establishConnection('w');

    $pairs = array();
    foreach ($updates as $id => $position) {
      // This is ugly because MySQL gets upset with us if it is configured
      // strictly and we attempt inserts which can't work. We'll never actually
      // do these inserts since they'll always collide (triggering the ON
      // DUPLICATE KEY logic), so we just provide dummy values in order to get
      // there.

      $pairs[] = qsprintf(
        $conn_w,
        '(%d, %d, "", "", "")',
        $id,
        $position->getSequence());
    }

    if ($pairs) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (id, sequence, boardPHID, columnPHID, objectPHID)
          VALUES %Q ON DUPLICATE KEY UPDATE sequence = VALUES(sequence)',
        $table->getTableName(),
        implode(', ', $pairs));
    }

    foreach ($adds as $position) {
      $position->save();
    }

    return $this;
  }

  private function loadBoards() {
    $viewer = $this->getViewer();
    $board_phids = $this->getBoardPHIDs();

    $boards = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($board_phids)
      ->execute();
    $boards = mpull($boards, null, 'getPHID');

    foreach ($boards as $key => $board) {
      if (!$board->getHasWorkboard()) {
        unset($boards[$key]);
      }
    }

    return $boards;
  }

  private function loadColumns(array $boards) {
    $viewer = $this->getViewer();

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array_keys($boards))
      ->execute();
    $columns = msort($columns, 'getSequence');
    $columns = mpull($columns, null, 'getPHID');

    $this->columnMap = $columns;
    $columns = mgroup($columns, 'getProjectPHID');

    return $columns;
  }

  private function loadPositions(array $boards) {
    $viewer = $this->getViewer();

    $object_phids = $this->getObjectPHIDs();
    if (!$object_phids) {
      return array();
    }

    $positions = id(new PhabricatorProjectColumnPositionQuery())
      ->setViewer($viewer)
      ->withBoardPHIDs(array_keys($boards))
      ->withObjectPHIDs($object_phids)
      ->execute();
    $positions = msort($positions, 'getOrderingKey');
    $positions = mgroup($positions, 'getBoardPHID');

    return $positions;
  }

  private function layoutBoard(
    $board,
    array $columns,
    array $positions) {

    $board_phid = $board->getPHID();
    $position_groups = mgroup($positions, 'getObjectPHID');

    $layout = array();
    foreach ($columns as $column) {
      $column_phid = $column->getPHID();
      $layout[$column_phid] = array();

      if ($column->isDefaultColumn()) {
        $default_phid = $column_phid;
      }
    }

    $object_phids = $this->getObjectPHIDs();
    foreach ($object_phids as $object_phid) {
      $positions = idx($position_groups, $object_phid, array());

      // Remove any positions in columns which no longer exist.
      foreach ($positions as $key => $position) {
        $column_phid = $position->getColumnPHID();
        if (empty($columns[$column_phid])) {
          $this->remQueue[] = $position;
          unset($positions[$key]);
        }
      }

      // If the object has no position, put it on the default column.
      if (!$positions) {
        $new_position = id(new PhabricatorProjectColumnPosition())
          ->setBoardPHID($board_phid)
          ->setColumnPHID($default_phid)
          ->setObjectPHID($object_phid)
          ->setSequence(0);

        $this->addQueue[] = $new_position;

        $positions = array(
          $new_position,
        );
      }

      foreach ($positions as $position) {
        $column_phid = $position->getColumnPHID();
        $layout[$column_phid][$object_phid] = $position;
      }
    }

    foreach ($layout as $column_phid => $map) {
      $map = msort($map, 'getOrderingKey');
      $layout[$column_phid] = $map;

      foreach ($map as $object_phid => $position) {
        $this->objectColumnMap[$board_phid][$object_phid][] = $column_phid;
      }
    }

    $this->boardLayout[$board_phid] = $layout;
  }

}
