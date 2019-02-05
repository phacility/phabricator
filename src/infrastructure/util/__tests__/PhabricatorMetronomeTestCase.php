<?php

final class PhabricatorMetronomeTestCase
  extends PhabricatorTestCase {

  public function testMetronomeOffsets() {
    $cases = array(
      'web001.example.net' => 44,
      'web002.example.net' => 36,
      'web003.example.net' => 25,
      'web004.example.net' => 25,
      'web005.example.net' => 16,
      'web006.example.net' => 26,
      'web007.example.net' => 35,
      'web008.example.net' => 14,
    );

    $metronome = id(new PhabricatorMetronome())
      ->setFrequency(60);

    foreach ($cases as $input => $expect) {
      $metronome->setOffsetFromSeed($input);

      $this->assertEqual(
        $expect,
        $metronome->getOffset(),
        pht('Offset for: %s', $input));
    }
  }

  public function testMetronomeTicks() {
    $metronome = id(new PhabricatorMetronome())
      ->setFrequency(60)
      ->setOffset(13);

    $tick_epoch = strtotime('2000-01-01 11:11:13 AM UTC');

    // Since the epoch is at "0:13" on the clock, the metronome should tick
    // then.
    $this->assertEqual(
      $tick_epoch,
      $metronome->getNextTickAfter($tick_epoch - 1),
      pht('Tick at 11:11:13 AM.'));

    // The next tick should be a minute later.
    $this->assertEqual(
      $tick_epoch + 60,
      $metronome->getNextTickAfter($tick_epoch),
      pht('Tick at 11:12:13 AM.'));


    // There's no tick in the next 59 seconds.
    $this->assertFalse(
      $metronome->didTickBetween($tick_epoch, $tick_epoch + 59));

    $this->assertTrue(
      $metronome->didTickBetween($tick_epoch, $tick_epoch + 60));
  }


}
