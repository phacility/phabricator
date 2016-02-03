<?php

final class PhabricatorBoardLayoutEngine extends Phobject {

  private $viewer;
  private $boardPHIDs;
  private $objectPHIDs;
  private $boards;
  private $columnMap;
  private $objectColumnMap = array();
  private $boardLayout = array();

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

  public function getObjectColumns($board_phid, $object_phid) {
    $board_map = idx($this->objectColumnMap, $board_phid, array());

    $column_phids = idx($board_map, $object_phid);
    if (!$column_phids) {
      return array();
    }

    return array_select_keys($this->columnMap, $column_phids);
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

    $positions = id(new PhabricatorProjectColumnPositionQuery())
      ->setViewer($viewer)
      ->withBoardPHIDs(array_keys($boards))
      ->withObjectPHIDs($this->getObjectPHIDs())
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

    foreach ($columns as $column) {
      if ($column->isDefaultColumn()) {
        $default_phid = $column->getPHID();
        break;
      }
    }

    $layout = array();

    $object_phids = $this->getObjectPHIDs();
    foreach ($object_phids as $object_phid) {
      $positions = idx($position_groups, $object_phid, array());

      // Remove any positions in columns which no longer exist.
      foreach ($positions as $key => $position) {
        $column_phid = $position->getColumnPHID();
        if (empty($columns[$column_phid])) {
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
