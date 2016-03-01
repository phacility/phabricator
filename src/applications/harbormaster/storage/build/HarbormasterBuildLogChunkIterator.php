<?php

final class HarbormasterBuildLogChunkIterator
  extends PhutilBufferedIterator {

  private $log;
  private $cursor;

  public function __construct(HarbormasterBuildLog $log) {
    $this->log = $log;
  }

  protected function didRewind() {
    $this->cursor = 0;
  }

  public function key() {
    return $this->current()->getID();
  }

  protected function loadPage() {
    $results = id(new HarbormasterBuildLogChunk())->loadAllWhere(
      'logID = %d AND id > %d ORDER BY id ASC LIMIT %d',
      $this->log->getID(),
      $this->cursor,
      $this->getPageSize());

    if ($results) {
      $this->cursor = last($results)->getID();
    }

    return $results;
  }

}
