<?php

final class PhrequentTimeBlockTestCase extends PhabricatorTestCase {

  public function testMergeTimeRanges() {

    // Overlapping ranges.
    $input = array(
      array(50, 150),
      array(100, 175),
    );
    $expect = array(
      array(50, 175),
    );

    $this->assertEqual($expect, PhrequentTimeBlock::mergeTimeRanges($input));


    // Identical ranges.
    $input = array(
      array(1, 1),
      array(1, 1),
      array(1, 1),
    );
    $expect = array(
      array(1, 1),
    );

    $this->assertEqual($expect, PhrequentTimeBlock::mergeTimeRanges($input));


    // Range which is a strict subset of another range.
    $input = array(
      array(2, 7),
      array(1, 10),
    );
    $expect = array(
      array(1, 10),
    );

    $this->assertEqual($expect, PhrequentTimeBlock::mergeTimeRanges($input));


    // These are discontinuous and should not be merged.
    $input = array(
      array(5, 6),
      array(7, 8),
    );
    $expect = array(
      array(5, 6),
      array(7, 8),
    );

    $this->assertEqual($expect, PhrequentTimeBlock::mergeTimeRanges($input));


    // These overlap only on an edge, but should merge.
    $input = array(
      array(5, 6),
      array(6, 7),
    );
    $expect = array(
      array(5, 7),
    );

    $this->assertEqual($expect, PhrequentTimeBlock::mergeTimeRanges($input));
  }

  public function testPreemptingEvents() {

    // Roughly, this is: got into work, started T1, had a meeting from 10-11,
    // left the office around 2 to meet a client at a coffee shop, worked on
    // T1 again for about 40 minutes, had the meeting from 3-3:30, finished up
    // on T1, headed back to the office, got another hour of work in, and
    // headed home.

    $event = $this->newEvent('T1', 900, 1700);

    $event->attachPreemptingEvents(
      array(
        $this->newEvent('meeting', 1000, 1100),
        $this->newEvent('offsite', 1400, 1600),
        $this->newEvent('T1', 1420, 1580),
        $this->newEvent('offsite meeting', 1500, 1550),
      ));

    $block = new PhrequentTimeBlock(array($event));

    $ranges = $block->getObjectTimeRanges();
    $ranges = $this->reduceRanges($ranges);

    $this->assertEqual(
      array(
        'T1' => array(
          array(900, 1000),  // Before morning meeting.
          array(1100, 1400), // After morning meeting.
          array(1420, 1500), // Coffee, before client meeting.
          array(1550, 1580), // Coffee, after client meeting.
          array(1600, 1700), // After returning from off site.
        ),
      ),
      $ranges);

    $event = $this->newEvent('T2', 100, 300);
    $event->attachPreemptingEvents(
      array(
        $this->newEvent('meeting', 200, null),
      ));

    $block = new PhrequentTimeBlock(array($event));

    $ranges = $block->getObjectTimeRanges();
    $ranges = $this->reduceRanges($ranges);

    $this->assertEqual(
      array(
        'T2' => array(
          array(100, 200),
        ),
      ),
      $ranges);
  }

  public function testTimelineSort() {
    $e1 = $this->newEvent('X1', 1, 1)->setID(1);

    $in = array(
      array(
        'event' => $e1,
        'at' => 1,
        'type' => 'start',
      ),
      array(
        'event' => $e1,
        'at' => 1,
        'type' => 'end',
      ),
    );

    usort($in, array('PhrequentTimeBlock', 'sortTimeline'));

    $this->assertEqual(
      array(
        'start',
        'end',
      ),
      ipull($in, 'type'));
  }

  public function testInstantaneousEvent() {

    $event = $this->newEvent('T1', 8, 8);
    $event->attachPreemptingEvents(array());

    $block = new PhrequentTimeBlock(array($event));

    $ranges = $block->getObjectTimeRanges();
    $ranges = $this->reduceRanges($ranges);

    $this->assertEqual(
      array(
        'T1' => array(
          array(8, 8),
        ),
      ),
      $ranges);
  }

  public function testPopAcrossStrata() {

    $event = $this->newEvent('T1', 1, 1000);
    $event->attachPreemptingEvents(
      array(
        $this->newEvent('T2', 100, 300),
        $this->newEvent('T1', 200, 400),
        $this->newEvent('T3', 250, 275),
      ));

    $block = new PhrequentTimeBlock(array($event));

    $ranges = $block->getObjectTimeRanges();
    $ranges = $this->reduceRanges($ranges);

    $this->assertEqual(
      array(
        'T1' => array(
          array(1, 100),
          array(200, 250),
          array(275, 1000),
        ),
      ),
      $ranges);
  }

  public function testEndDeeperStratum() {
    $event = $this->newEvent('T1', 1, 1000);
    $event->attachPreemptingEvents(
      array(
        $this->newEvent('T2', 100, 900),
        $this->newEvent('T1', 200, 400),
        $this->newEvent('T3', 300, 800),
        $this->newEvent('T1', 350, 600),
        $this->newEvent('T4', 380, 390),
      ));

    $block = new PhrequentTimeBlock(array($event));

    $ranges = $block->getObjectTimeRanges();
    $ranges = $this->reduceRanges($ranges);

    $this->assertEqual(
      array(
        'T1' => array(
          array(1, 100),
          array(200, 300),
          array(350, 380),
          array(390, 600),
          array(900, 1000),
        ),
      ),
      $ranges);
  }

  public function testOngoing() {
    $event = $this->newEvent('T1', 1, null);
    $event->attachPreemptingEvents(array());

    $block = new PhrequentTimeBlock(array($event));

    $ranges = $block->getObjectTimeRanges();
    $ranges = $this->reduceRanges($ranges);

    $this->assertEqual(
      array(
        'T1' => array(
          array(1, null),
        ),
      ),
      $ranges);
  }

  public function testOngoingInterrupted() {
    $event = $this->newEvent('T1', 1, null);
    $event->attachPreemptingEvents(
      array(
        $this->newEvent('T2', 100, 900),
      ));

    $block = new PhrequentTimeBlock(array($event));

    $ranges = $block->getObjectTimeRanges();
    $ranges = $this->reduceRanges($ranges);

    $this->assertEqual(
      array(
        'T1' => array(
          array(1, 100),
          array(900, null),
        ),
      ),
      $ranges);
  }

  public function testOngoingPreempted() {
    $event = $this->newEvent('T1', 1, null);
    $event->attachPreemptingEvents(
      array(
        $this->newEvent('T2', 100, null),
      ));

    $block = new PhrequentTimeBlock(array($event));

    $ranges = $block->getObjectTimeRanges();
    $ranges = $this->reduceRanges($ranges);

    $this->assertEqual(
      array(
        'T1' => array(
          array(1, 100),
        ),
      ),
      $ranges);
  }

  public function testSumTimeSlices() {
    // This block has multiple closed slices.
    $block = new PhrequentTimeBlock(
      array(
        $this->newEvent('T1', 3456, 4456)->attachPreemptingEvents(array()),
        $this->newEvent('T1', 8000, 9000)->attachPreemptingEvents(array()),
      ));

    $this->assertEqual(
      2000,
      $block->getTimeSpentOnObject('T1', 10000));

    // This block has an open slice.
    $block = new PhrequentTimeBlock(
      array(
        $this->newEvent('T1', 3456, 4456)->attachPreemptingEvents(array()),
        $this->newEvent('T1', 8000, null)->attachPreemptingEvents(array()),
      ));

    $this->assertEqual(
      3000,
      $block->getTimeSpentOnObject('T1', 10000));
  }

  private function newEvent($object_phid, $start_time, $end_time) {
    static $id = 0;

    return id(new PhrequentUserTime())
      ->setID(++$id)
      ->setObjectPHID($object_phid)
      ->setDateStarted($start_time)
      ->setDateEnded($end_time);
  }

  private function reduceRanges(array $ranges) {
    $results = array();

    foreach ($ranges as $phid => $slices) {
      $results[$phid] = $slices->getRanges();
    }

    return $results;
  }

}
