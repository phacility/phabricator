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

    $ranges = $block->getObjectTimeRanges(1800);
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

    $ranges = $block->getObjectTimeRanges(1000);
    $this->assertEqual(
      array(
        'T2' => array(
          array(100, 200),
        ),
      ),
      $ranges);
  }

  private function newEvent($object_phid, $start_time, $end_time) {
    return id(new PhrequentUserTime())
      ->setObjectPHID($object_phid)
      ->setDateStarted($start_time)
      ->setDateEnded($end_time);
  }

}
