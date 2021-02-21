<?php

/**
 * Datastructure which follows lines of code across source changes.
 *
 * This map is used to update the positions of inline comments after diff
 * updates. For example, if a inline comment appeared on line 30 of a diff
 * but the next update adds 15 more lines above it, the comment should move
 * down to line 45.
 *
 */
final class DifferentialLineAdjustmentMap extends Phobject {

  private $map;
  private $nearestMap;
  private $isInverse;
  private $finalOffset;
  private $nextMapInChain;

  /**
   * Get the raw adjustment map.
   */
  public function getMap() {
    return $this->map;
  }

  public function getNearestMap() {
    if ($this->nearestMap === null) {
      $this->buildNearestMap();
    }

    return $this->nearestMap;
  }

  public function getFinalOffset() {
    // Make sure we've built this map already.
    $this->getNearestMap();
    return $this->finalOffset;
  }


  /**
   * Add a map to the end of the chain.
   *
   * When a line is mapped with @{method:mapLine}, it is mapped through all
   * maps in the chain.
   */
  public function addMapToChain(DifferentialLineAdjustmentMap $map) {
    if ($this->nextMapInChain) {
      $this->nextMapInChain->addMapToChain($map);
    } else {
      $this->nextMapInChain = $map;
    }
    return $this;
  }


  /**
   * Map a line across a change, or a series of changes.
   *
   * @param int Line to map
   * @param bool True to map it as the end of a range.
   * @return wild Spooky magic.
   */
  public function mapLine($line, $is_end) {
    $nmap = $this->getNearestMap();

    $deleted = false;
    $offset = false;
    if (isset($nmap[$line])) {
      $line_range = $nmap[$line];
      if ($is_end) {
        $to_line = end($line_range);
      } else {
        $to_line = reset($line_range);
      }
      if ($to_line <= 0) {
        // If we're tracing the first line and this block is collapsing,
        // compute the offset from the top of the block.
        if (!$is_end && $this->isInverse) {
          $offset = 1;
          $cursor = $line - 1;
          while (isset($nmap[$cursor])) {
            $prev = $nmap[$cursor];
            $prev = reset($prev);
            if ($prev == $to_line) {
              $offset++;
            } else {
              break;
            }
            $cursor--;
          }
        }

        $to_line = -$to_line;
        if (!$this->isInverse) {
          $deleted = true;
        }
      }
      $line = $to_line;
    } else {
      $line = $line + $this->finalOffset;
    }

    if ($this->nextMapInChain) {
      $chain = $this->nextMapInChain->mapLine($line, $is_end);
      list($chain_deleted, $chain_offset, $line) = $chain;
      $deleted = ($deleted || $chain_deleted);
      if ($chain_offset !== false) {
        if ($offset === false) {
          $offset = 0;
        }
        $offset += $chain_offset;
      }
    }

    return array($deleted, $offset, $line);
  }


  /**
   * Build a derived map which maps deleted lines to the nearest valid line.
   *
   * This computes a "nearest line" map and a final-line offset. These
   * derived maps allow us to map deleted code to the previous (or next) line
   * which actually exists.
   */
  private function buildNearestMap() {
    $map = $this->map;
    $nmap = array();

    $nearest = 0;
    foreach ($map as $key => $value) {
      if ($value) {
        $nmap[$key] = $value;
        $nearest = end($value);
      } else {
        $nmap[$key][0] = -$nearest;
      }
    }

    if (isset($key)) {
      $this->finalOffset = ($nearest - $key);
    } else {
      $this->finalOffset = 0;
    }

    foreach (array_reverse($map, true) as $key => $value) {
      if ($value) {
        $nearest = reset($value);
      } else {
        $nmap[$key][1] = -$nearest;
      }
    }

    $this->nearestMap = $nmap;

    return $this;
  }

  public static function newFromHunks(array $hunks) {
    assert_instances_of($hunks, 'DifferentialHunk');

    $map = array();
    $o = 0;
    $n = 0;

    $hunks = msort($hunks, 'getOldOffset');
    foreach ($hunks as $hunk) {

      // If the hunks are disjoint, add the implied missing lines where
      // nothing changed.
      $min = ($hunk->getOldOffset() - 1);
      while ($o < $min) {
        $o++;
        $n++;
        $map[$o][] = $n;
      }

      $lines = $hunk->getStructuredLines();
      foreach ($lines as $line) {
        switch ($line['type']) {
          case '-':
            $o++;
            $map[$o] = array();
            break;
          case '+':
            $n++;
            $map[$o][] = $n;
            break;
          case ' ':
            $o++;
            $n++;
            $map[$o][] = $n;
            break;
          default:
            break;
        }
      }
    }

    $map = self::reduceMapRanges($map);

    return self::newFromMap($map);
  }

  public static function newFromMap(array $map) {
    $obj = new DifferentialLineAdjustmentMap();
    $obj->map = $map;
    return $obj;
  }

  public static function newInverseMap(DifferentialLineAdjustmentMap $map) {
    $old = $map->getMap();
    $inv = array();
    $last = 0;
    foreach ($old as $k => $v) {
      if (count($v) > 1) {
        $v = range(reset($v), end($v));
      }
      if ($k == 0) {
        foreach ($v as $line) {
          $inv[$line] = array();
          $last = $line;
        }
      } else if ($v) {
        $first = true;
        foreach ($v as $line) {
          if ($first) {
            $first = false;
            $inv[$line][] = $k;
            $last = $line;
          } else {
            $inv[$line] = array();
          }
        }
      } else {
        $inv[$last][] = $k;
      }
    }

    $inv = self::reduceMapRanges($inv);

    $obj = new DifferentialLineAdjustmentMap();
    $obj->map = $inv;
    $obj->isInverse = !$map->isInverse;
    return $obj;
  }

  private static function reduceMapRanges(array $map) {
    foreach ($map as $key => $values) {
      if (count($values) > 2) {
        $map[$key] = array(reset($values), end($values));
      }
    }
    return $map;
  }


  public static function loadMaps(array $maps) {
    $keys = array();
    foreach ($maps as $map) {
      list($u, $v) = $map;
      $keys[self::getCacheKey($u, $v)] = $map;
    }

    $cache = new PhabricatorKeyValueDatabaseCache();
    $cache = new PhutilKeyValueCacheProfiler($cache);
    $cache->setProfiler(PhutilServiceProfiler::getInstance());

    $results = array();

    if ($keys) {
      $caches = $cache->getKeys(array_keys($keys));
      foreach ($caches as $key => $value) {
        list($u, $v) = $keys[$key];
        try {
          $results[$u][$v] = self::newFromMap(
            phutil_json_decode($value));
        } catch (Exception $ex) {
          // Ignore, rebuild below.
        }
        unset($keys[$key]);
      }
    }

    if ($keys) {
      $built = self::buildMaps($maps);

      $write = array();
      foreach ($built as $u => $list) {
        foreach ($list as $v => $map) {
          $write[self::getCacheKey($u, $v)] = json_encode($map->getMap());
          $results[$u][$v] = $map;
        }
      }

      $cache->setKeys($write);
    }

    return $results;
  }

  private static function buildMaps(array $maps) {
    $need = array();
    foreach ($maps as $map) {
      list($u, $v) = $map;
      $need[$u] = $u;
      $need[$v] = $v;
    }

    if ($need) {
      $changesets = id(new DifferentialChangesetQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withIDs($need)
        ->needHunks(true)
        ->execute();
      $changesets = mpull($changesets, null, 'getID');
    }

    $results = array();
    foreach ($maps as $map) {
      list($u, $v) = $map;
      $u_set = idx($changesets, $u);
      $v_set = idx($changesets, $v);

      if (!$u_set || !$v_set) {
        continue;
      }

      // This is the simple case.
      if ($u == $v) {
        $results[$u][$v] = self::newFromHunks(
          $u_set->getHunks());
        continue;
      }

      $u_old = $u_set->makeOldFile();
      $v_old = $v_set->makeOldFile();

      // No difference between the two left sides.
      if ($u_old == $v_old) {
        $results[$u][$v] = self::newFromMap(
          array());
        continue;
      }

      // If we're missing context, this won't currently work. We can
      // make this case work, but it's fairly rare.
      $u_hunks = $u_set->getHunks();
      $v_hunks = $v_set->getHunks();
      if (count($u_hunks) != 1 ||
          count($v_hunks) != 1 ||
          head($u_hunks)->getOldOffset() != 1 ||
          head($u_hunks)->getNewOffset() != 1 ||
          head($v_hunks)->getOldOffset() != 1 ||
          head($v_hunks)->getNewOffset() != 1) {
        continue;
      }

      $changeset = id(new PhabricatorDifferenceEngine())
        ->generateChangesetFromFileContent($u_old, $v_old);

      $results[$u][$v] = self::newFromHunks(
        $changeset->getHunks());
    }

    return $results;
  }

  private static function getCacheKey($u, $v) {
    return 'diffadjust.v1('.$u.','.$v.')';
  }

}
