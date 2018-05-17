<?php

final class HarbormasterBuildLogChunkIterator
  extends PhutilBufferedIterator {

  private $log;
  private $cursor;
  private $asString;

  private $min = 0;
  private $max = PHP_INT_MAX;

  public function __construct(HarbormasterBuildLog $log) {
    $this->log = $log;
  }

  protected function didRewind() {
    $this->cursor = $this->min;
  }

  public function key() {
    return $this->current()->getID();
  }

  public function setRange($min, $max) {
    $this->min = (int)$min;
    $this->max = (int)$max;
    return $this;
  }

  public function setAsString($as_string) {
    $this->asString = $as_string;
    return $this;
  }

  protected function loadPage() {
    if ($this->cursor > $this->max) {
      return array();
    }

    $results = id(new HarbormasterBuildLogChunk())->loadAllWhere(
      'logID = %d AND id >= %d AND id <= %d ORDER BY id ASC LIMIT %d',
      $this->log->getID(),
      $this->cursor,
      $this->max,
      $this->getPageSize());

    if ($results) {
      $this->cursor = last($results)->getID() + 1;
    }

    if ($this->asString) {
      return mpull($results, 'getChunkDisplayText');
    } else {
      return $results;
    }
  }

}
