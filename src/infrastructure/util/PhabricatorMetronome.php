<?php

/**
 * Tick at a given frequency with a specifiable offset.
 *
 * One use case for this is to flatten out load spikes caused by periodic
 * service calls. Give each host a metronome that ticks at the same frequency,
 * but with different offsets. Then, have hosts make service calls only after
 * their metronome ticks. This spreads service calls out evenly more quickly
 * and more predictably than adding random jitter.
 */
final class PhabricatorMetronome
  extends Phobject {

  private $offset = 0;
  private $frequency;

  public function setOffset($offset) {
    if (!is_int($offset)) {
      throw new Exception(pht('Metronome offset must be an integer.'));
    }

    if ($offset < 0) {
      throw new Exception(pht('Metronome offset must be 0 or more.'));
    }

    // We're not requiring that the offset be smaller than the frequency. If
    // the offset is larger, we'll just clamp it to the frequency before we
    // use it. This allows the offset to be configured before the frequency
    // is configured, which is useful for using a hostname as an offset seed.

    $this->offset = $offset;

    return $this;
  }

  public function setFrequency($frequency) {
    if (!is_int($frequency)) {
      throw new Exception(pht('Metronome frequency must be an integer.'));
    }

    if ($frequency < 1) {
      throw new Exception(pht('Metronome frequency must be 1 or more.'));
    }

    $this->frequency = $frequency;

    return $this;
  }

  public function setOffsetFromSeed($seed) {
    $offset = PhabricatorHash::digestToRange($seed, 0, 0x7FFFFFFF);
    return $this->setOffset($offset);
  }

  public function getFrequency() {
    if ($this->frequency === null) {
      throw new PhutilInvalidStateException('setFrequency');
    }
    return $this->frequency;
  }

  public function getOffset() {
    $frequency = $this->getFrequency();
    return ($this->offset % $frequency);
  }

  public function getNextTickAfter($epoch) {
    $frequency = $this->getFrequency();
    $offset = $this->getOffset();

    $remainder = ($epoch % $frequency);

    if ($remainder < $offset) {
      return ($epoch - $remainder) + $offset;
    } else {
      return ($epoch - $remainder) + $frequency + $offset;
    }
  }

  public function didTickBetween($min, $max) {
    if ($max < $min) {
      throw new Exception(
        pht(
          'Maximum tick window must not be smaller than minimum tick window.'));
    }

    $next = $this->getNextTickAfter($min);
    return ($next <= $max);
  }

}
