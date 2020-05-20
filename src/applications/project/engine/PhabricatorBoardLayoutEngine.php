<?php

final class PhabricatorBoardLayoutEngine extends Phobject {

  private $viewer;
  private $boardPHIDs;
  private $objectPHIDs = array();
  private $boards;
  private $columnMap = array();
  private $objectColumnMap = array();
  private $boardLayout = array();
  private $fetchAllBoards;

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
    $this->boardPHIDs = array_fuse($board_phids);
    return $this;
  }

  public function getBoardPHIDs() {
    return $this->boardPHIDs;
  }

  public function setObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = array_fuse($object_phids);
    return $this;
  }

  public function getObjectPHIDs() {
    return $this->objectPHIDs;
  }

  /**
   * Fetch all boards, even if the board is disabled.
   */
  public function setFetchAllBoards($fetch_all) {
    $this->fetchAllBoards = $fetch_all;
    return $this;
  }

  public function getFetchAllBoards() {
    return $this->fetchAllBoards;
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

  public function getColumnObjectPositions($board_phid, $column_phid) {
    $columns = idx($this->boardLayout, $board_phid, array());
    return idx($columns, $column_phid, array());
  }


  public function getColumnObjectPHIDs($board_phid, $column_phid) {
    $positions = $this->getColumnObjectPositions($board_phid, $column_phid);
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

  public function queueAddPosition(
    $board_phid,
    $column_phid,
    $object_phid,
    array $after_phids,
    array $before_phids) {

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

    if (!$positions) {
      $object_position->setSequence(0);
    } else {
      // The user's view of the board may fall out of date, so they might
      // try to drop a card under a different card which is no longer where
      // they thought it was.

      // When this happens, we perform the move anyway, since this is almost
      // certainly what users want when interacting with the UI. We'l try to
      // fall back to another nearby card if the client provided us one. If
      // we don't find any of the cards the client specified in the column,
      // we'll just move the card to the default position.

      $search_phids = array();
      foreach ($after_phids as $after_phid) {
        $search_phids[] = array($after_phid, false);
      }

      foreach ($before_phids as $before_phid) {
        $search_phids[] = array($before_phid, true);
      }

      // This makes us fall back to the default position if we fail every
      // candidate position. The default position counts as a "before" position
      // because we want to put the new card at the top of the column.
      $search_phids[] = array(null, true);

      $found = false;
      foreach ($search_phids as $search_position) {
        list($relative_phid, $is_before) = $search_position;
        foreach ($positions as $position) {
          if (!$found) {
            if ($relative_phid === null) {
              $is_match = true;
            } else {
              $position_phid = $position->getObjectPHID();
              $is_match = ($relative_phid === $position_phid);
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

        if ($found) {
          break;
        }
      }
    }

    $this->addQueue[] = $object_position;

    $positions[$object_phid] = $object_position;
    $positions = msortv($positions, 'newColumnPositionOrderVector');

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
          VALUES %LQ ON DUPLICATE KEY UPDATE sequence = VALUES(sequence)',
        $table->getTableName(),
        $pairs);
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
      if (!($board instanceof PhabricatorWorkboardInterface)) {
        unset($boards[$key]);
      }
    }

    if (!$this->fetchAllBoards) {
      foreach ($boards as $key => $board) {
        if (!$board->getHasWorkboard()) {
          unset($boards[$key]);
        }
      }
    }

    return $boards;
  }

  private function loadColumns(array $boards) {
    $viewer = $this->getViewer();

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array_keys($boards))
      ->needTriggers(true)
      ->execute();
    $columns = msort($columns, 'getOrderingKey');
    $columns = mpull($columns, null, 'getPHID');

    $need_children = array();
    foreach ($boards as $phid => $board) {
      if ($board->getHasMilestones() || $board->getHasSubprojects()) {
        $need_children[] = $phid;
      }
    }

    if ($need_children) {
      $children = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withParentProjectPHIDs($need_children)
        ->execute();
      $children = mpull($children, null, 'getPHID');
      $children = mgroup($children, 'getParentProjectPHID');
    } else {
      $children = array();
    }

    $columns = mgroup($columns, 'getProjectPHID');
    foreach ($boards as $board_phid => $board) {
      $board_columns = idx($columns, $board_phid, array());

      // If the project has milestones, create any missing columns.
      if ($board->getHasMilestones() || $board->getHasSubprojects()) {
        $child_projects = idx($children, $board_phid, array());

        if ($board_columns) {
          $next_sequence = last($board_columns)->getSequence() + 1;
        } else {
          $next_sequence = 1;
        }

        $proxy_columns = mpull($board_columns, null, 'getProxyPHID');
        foreach ($child_projects as $child_phid => $child) {
          if (isset($proxy_columns[$child_phid])) {
            continue;
          }

          $new_column = PhabricatorProjectColumn::initializeNewColumn($viewer)
            ->attachProject($board)
            ->attachProxy($child)
            ->setSequence($next_sequence++)
            ->setProjectPHID($board_phid)
            ->setProxyPHID($child_phid);

          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
            $new_column->save();
          unset($unguarded);

          $board_columns[$new_column->getPHID()] = $new_column;
        }
      }

      $board_columns = msort($board_columns, 'getOrderingKey');

      $columns[$board_phid] = $board_columns;
    }

    foreach ($columns as $board_phid => $board_columns) {
      foreach ($board_columns as $board_column) {
        $column_phid = $board_column->getPHID();
        $this->columnMap[$column_phid] = $board_column;
      }
    }

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
    $positions = msortv($positions, 'newColumnPositionOrderVector');
    $positions = mgroup($positions, 'getBoardPHID');

    return $positions;
  }

  private function layoutBoard(
    $board,
    array $columns,
    array $positions) {

    $viewer = $this->getViewer();

    $board_phid = $board->getPHID();
    $position_groups = mgroup($positions, 'getObjectPHID');

    $layout = array();
    $default_phid = null;
    foreach ($columns as $column) {
      $column_phid = $column->getPHID();
      $layout[$column_phid] = array();

      if ($column->isDefaultColumn()) {
        $default_phid = $column_phid;
      }
    }

    // Find all the columns which are proxies for other objects.
    $proxy_map = array();
    foreach ($columns as $column) {
      $proxy_phid = $column->getProxyPHID();
      if ($proxy_phid) {
        $proxy_map[$proxy_phid] = $column->getPHID();
      }
    }

    $object_phids = $this->getObjectPHIDs();

    // If we have proxies, we need to force cards into the correct proxy
    // columns.
    if ($proxy_map && $object_phids) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($object_phids)
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));
      $edge_query->execute();

      $project_phids = $edge_query->getDestinationPHIDs();
      $project_phids = array_fuse($project_phids);
    } else {
      $project_phids = array();
    }

    if ($project_phids) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withPHIDs($project_phids)
        ->execute();
      $projects = mpull($projects, null, 'getPHID');
    } else {
      $projects = array();
    }

    // Build a map from every project that any task is tagged with to the
    // ancestor project which has a column on this board, if one exists.
    $ancestor_map = array();
    foreach ($projects as $phid => $project) {
      if (isset($proxy_map[$phid])) {
        $ancestor_map[$phid] = $proxy_map[$phid];
      } else {
        $seen = array($phid);
        foreach ($project->getAncestorProjects() as $ancestor) {
          $ancestor_phid = $ancestor->getPHID();
          $seen[] = $ancestor_phid;
          if (isset($proxy_map[$ancestor_phid])) {
            foreach ($seen as $project_phid) {
              $ancestor_map[$project_phid] = $proxy_map[$ancestor_phid];
            }
          }
        }
      }
    }

    $view_sequence = 1;
    foreach ($object_phids as $object_phid) {
      $positions = idx($position_groups, $object_phid, array());

      // First, check for objects that have corresponding proxy columns. We're
      // going to overwrite normal column positions if a tag belongs to a proxy
      // column, since you can't be in normal columns if you're in proxy
      // columns.
      $proxy_hits = array();
      if ($proxy_map) {
        $object_project_phids = $edge_query->getDestinationPHIDs(
          array(
            $object_phid,
          ));

        foreach ($object_project_phids as $project_phid) {
          if (isset($ancestor_map[$project_phid])) {
            $proxy_hits[] = $ancestor_map[$project_phid];
          }
        }
      }

      if ($proxy_hits) {
        // TODO: For now, only one column hit is permissible.
        $proxy_hits = array_slice($proxy_hits, 0, 1);

        $proxy_hits = array_fuse($proxy_hits);

        // Check the object positions: we hope to find a position in each
        // column the object should be part of. We're going to drop any
        // invalid positions and create new positions where positions are
        // missing.
        foreach ($positions as $key => $position) {
          $column_phid = $position->getColumnPHID();
          if (isset($proxy_hits[$column_phid])) {
            // Valid column, mark the position as found.
            unset($proxy_hits[$column_phid]);
          } else {
            // Invalid column, ignore the position.
            unset($positions[$key]);
          }
        }

        // Create new positions for anything we haven't found.
        foreach ($proxy_hits as $proxy_hit) {
          $new_position = id(new PhabricatorProjectColumnPosition())
            ->setBoardPHID($board_phid)
            ->setColumnPHID($proxy_hit)
            ->setObjectPHID($object_phid)
            ->setSequence(0)
            ->setViewSequence($view_sequence++);

          $this->addQueue[] = $new_position;

          $positions[] = $new_position;
        }
      } else {
        // Ignore any positions in columns which no longer exist. We don't
        // actively destory them because the rest of the code ignores them and
        // there's no real need to destroy the data.
        foreach ($positions as $key => $position) {
          $column_phid = $position->getColumnPHID();
          if (empty($columns[$column_phid])) {
            unset($positions[$key]);
          }
        }

        // If the object has no position, put it on the default column if
        // one exists.
        if (!$positions && $default_phid) {
          $new_position = id(new PhabricatorProjectColumnPosition())
            ->setBoardPHID($board_phid)
            ->setColumnPHID($default_phid)
            ->setObjectPHID($object_phid)
            ->setSequence(0)
            ->setViewSequence($view_sequence++);

          $this->addQueue[] = $new_position;

          $positions = array(
            $new_position,
          );
        }
      }

      foreach ($positions as $position) {
        $column_phid = $position->getColumnPHID();
        $layout[$column_phid][$object_phid] = $position;
      }
    }

    foreach ($layout as $column_phid => $map) {
      $map = msortv($map, 'newColumnPositionOrderVector');
      $layout[$column_phid] = $map;

      foreach ($map as $object_phid => $position) {
        $this->objectColumnMap[$board_phid][$object_phid][] = $column_phid;
      }
    }

    $this->boardLayout[$board_phid] = $layout;
  }

}
