<?php

final class DifferentialAdjustmentMapTestCase extends PhutilTestCase {

  public function testBasicMaps() {
    $change_map = array(
      1 => array(1),
      2 => array(2),
      3 => array(3),
      4 => array(),
      5 => array(),
      6 => array(),
      7 => array(4),
      8 => array(5),
      9 => array(6),
      10 => array(7),
      11 => array(8),
      12 => array(9),
      13 => array(10),
      14 => array(11),
      15 => array(12),
      16 => array(13),
      17 => array(14),
      18 => array(15),
      19 => array(16),
      20 => array(17, 20),
      21 => array(21),
      22 => array(22),
      23 => array(23),
      24 => array(24),
      25 => array(25),
      26 => array(26),
    );

    $hunks = $this->loadHunks('add.diff');
    $this->assertEqual(
      array(
        0 => array(1, 26),
      ),
      DifferentialLineAdjustmentMap::newFromHunks($hunks)->getMap());

    $hunks = $this->loadHunks('change.diff');
    $this->assertEqual(
      $change_map,
      DifferentialLineAdjustmentMap::newFromHunks($hunks)->getMap());

    $hunks = $this->loadHunks('remove.diff');
    $this->assertEqual(
      array_fill_keys(range(1, 26), array()),
      DifferentialLineAdjustmentMap::newFromHunks($hunks)->getMap());

    // With the contextless diff, we don't get the last few similar lines
    // in the map.
    $reduced_map = $change_map;
    unset($reduced_map[24]);
    unset($reduced_map[25]);
    unset($reduced_map[26]);

    $hunks = $this->loadHunks('context.diff');
    $this->assertEqual(
      $reduced_map,
      DifferentialLineAdjustmentMap::newFromHunks($hunks)->getMap());
  }


  public function testInverseMaps() {
    $change_map = array(
      1 => array(1),
      2 => array(2),
      3 => array(3, 6),
      4 => array(7),
      5 => array(8),
      6 => array(9),
      7 => array(10),
      8 => array(11),
      9 => array(12),
      10 => array(13),
      11 => array(14),
      12 => array(15),
      13 => array(16),
      14 => array(17),
      15 => array(18),
      16 => array(19),
      17 => array(20),
      18 => array(),
      19 => array(),
      20 => array(),
      21 => array(21),
      22 => array(22),
      23 => array(23),
      24 => array(24),
      25 => array(25),
      26 => array(26),
    );

    $hunks = $this->loadHunks('add.diff');
    $this->assertEqual(
      array_fill_keys(range(1, 26), array()),
      DifferentialLineAdjustmentMap::newInverseMap(
        DifferentialLineAdjustmentMap::newFromHunks($hunks))->getMap());

    $hunks = $this->loadHunks('change.diff');
    $this->assertEqual(
      $change_map,
      DifferentialLineAdjustmentMap::newInverseMap(
        DifferentialLineAdjustmentMap::newFromHunks($hunks))->getMap());

    $hunks = $this->loadHunks('remove.diff');
    $this->assertEqual(
      array(
        0 => array(1, 26),
      ),
      DifferentialLineAdjustmentMap::newInverseMap(
        DifferentialLineAdjustmentMap::newFromHunks($hunks))->getMap());

    // With the contextless diff, we don't get the last few similar lines
    // in the map.
    $reduced_map = $change_map;
    unset($reduced_map[24]);
    unset($reduced_map[25]);
    unset($reduced_map[26]);

    $hunks = $this->loadHunks('context.diff');
    $this->assertEqual(
      $reduced_map,
      DifferentialLineAdjustmentMap::newInverseMap(
        DifferentialLineAdjustmentMap::newFromHunks($hunks))->getMap());
  }


  public function testNearestMaps() {
    $change_map = array(
      1 => array(1),
      2 => array(2),
      3 => array(3),
      4 => array(-3, -4),
      5 => array(-3, -4),
      6 => array(-3, -4),
      7 => array(4),
      8 => array(5),
      9 => array(6),
      10 => array(7),
      11 => array(8),
      12 => array(9),
      13 => array(10),
      14 => array(11),
      15 => array(12),
      16 => array(13),
      17 => array(14),
      18 => array(15),
      19 => array(16),
      20 => array(17, 20),
      21 => array(21),
      22 => array(22),
      23 => array(23),
      24 => array(24),
      25 => array(25),
      26 => array(26),
    );

    $hunks = $this->loadHunks('add.diff');
    $map = DifferentialLineAdjustmentMap::newFromHunks($hunks);
    $this->assertEqual(
      array(
        0 => array(1, 26),
      ),
      $map->getNearestMap());
    $this->assertEqual(26, $map->getFinalOffset());


    $hunks = $this->loadHunks('change.diff');
    $map = DifferentialLineAdjustmentMap::newFromHunks($hunks);
    $this->assertEqual(
      $change_map,
      $map->getNearestMap());
    $this->assertEqual(0, $map->getFinalOffset());


    $hunks = $this->loadHunks('remove.diff');
    $map = DifferentialLineAdjustmentMap::newFromHunks($hunks);
    $this->assertEqual(
      array_fill_keys(
        range(1, 26),
        array(0, 0)),
      $map->getNearestMap());
    $this->assertEqual(-26, $map->getFinalOffset());


    $reduced_map = $change_map;
    unset($reduced_map[24]);
    unset($reduced_map[25]);
    unset($reduced_map[26]);

    $hunks = $this->loadHunks('context.diff');
    $map = DifferentialLineAdjustmentMap::newFromHunks($hunks);
    $this->assertEqual(
      $reduced_map,
      $map->getNearestMap());
    $this->assertEqual(0, $map->getFinalOffset());


    $hunks = $this->loadHunks('insert.diff');
    $map = DifferentialLineAdjustmentMap::newFromHunks($hunks);
    $this->assertEqual(
      array(
        1 => array(1),
        2 => array(2),
        3 => array(3),
        4 => array(4),
        5 => array(5),
        6 => array(6),
        7 => array(7),
        8 => array(8),
        9 => array(9),
        10 => array(10, 13),
        11 => array(14),
        12 => array(15),
        13 => array(16),
      ),
      $map->getNearestMap());
    $this->assertEqual(3, $map->getFinalOffset());
  }


  public function testChainMaps() {
    // This test simulates porting inlines forward across a rebase.
    // Part 1 is the original diff.
    // Part 2 is the rebase, which we would normally compute synthetically.
    // Part 3 is the updated diff against the rebased changes.

    $diff1 = $this->loadHunks('chain.adjust.1.diff');
    $diff2 = $this->loadHunks('chain.adjust.2.diff');
    $diff3 = $this->loadHunks('chain.adjust.3.diff');

    $map = DifferentialLineAdjustmentMap::newInverseMap(
      DifferentialLineAdjustmentMap::newFromHunks($diff1));

    $map->addMapToChain(
        DifferentialLineAdjustmentMap::newFromHunks($diff2));

    $map->addMapToChain(
      DifferentialLineAdjustmentMap::newFromHunks($diff3));

    $actual = array();
    for ($ii = 1; $ii <= 13; $ii++) {
      $actual[$ii] = array(
        $map->mapLine($ii, false),
        $map->mapLine($ii, true),
      );
    }

    $this->assertEqual(
      array(
        1 => array(array(false, false, 1), array(false, false, 1)),
        2 => array(array(true, false, 1), array(true, false, 2)),
        3 => array(array(true, false, 1), array(true, false, 2)),
        4 => array(array(false, false, 2), array(false, false, 2)),
        5 => array(array(false, false, 3), array(false, false, 3)),
        6 => array(array(false, false, 4), array(false, false, 4)),
        7 => array(array(false, false, 5), array(false, false, 8)),
        8 => array(array(false, 0, 5), array(false, false, 9)),
        9 => array(array(false, 1, 5), array(false, false, 9)),
        10 => array(array(false, 2, 5), array(false, false, 9)),
        11 => array(array(false, false, 9), array(false, false, 9)),
        12 => array(array(false, false, 10), array(false, false, 10)),
        13 => array(array(false, false, 11), array(false, false, 11)),
      ),
      $actual);
  }


  private function loadHunks($name) {
    $root = dirname(__FILE__).'/map/';
    $data = Filesystem::readFile($root.$name);

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($data);

    $viewer = PhabricatorUser::getOmnipotentUser();
    $diff = DifferentialDiff::newFromRawChanges($viewer, $changes);

    $changesets = $diff->getChangesets();
    if (count($changesets) !== 1) {
      throw new Exception(
        pht(
          'Expected exactly one changeset from "%s".',
          $name));
    }
    $changeset = head($changesets);

    return $changeset->getHunks();
  }

}
