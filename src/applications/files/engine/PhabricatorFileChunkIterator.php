<?php

final class PhabricatorFileChunkIterator
  extends Phobject
  implements Iterator {

  private $chunks;
  private $cursor;
  private $begin;
  private $end;
  private $data;

  public function __construct(array $chunks, $begin = null, $end = null) {
    $chunks = msort($chunks, 'getByteStart');
    $this->chunks = $chunks;

    if ($begin !== null) {
      foreach ($chunks as $key => $chunk) {
        if ($chunk->getByteEnd() >= $begin) {
          unset($chunks[$key]);
        }
        break;
      }
      $this->begin = $begin;
    }

    if ($end !== null) {
      foreach ($chunks as $key => $chunk) {
        if ($chunk->getByteStart() <= $end) {
          unset($chunks[$key]);
        }
      }
      $this->end = $end;
    }
  }

  public function current() {
    $chunk = head($this->chunks);
    $data = $chunk->getDataFile()->loadFileData();

    if ($this->end !== null) {
      if ($chunk->getByteEnd() > $this->end) {
        $data = substr($data, 0, ($this->end - $chunk->getByteStart()));
      }
    }

    if ($this->begin !== null) {
      if ($chunk->getByteStart() < $this->begin) {
        $data = substr($data, ($this->begin - $chunk->getByteStart()));
      }
    }

    return $data;
  }

  public function key() {
    return head_key($this->chunks);
  }

  public function next() {
    unset($this->chunks[$this->key()]);
  }

  public function rewind() {
    return;
  }

  public function valid() {
    return (count($this->chunks) > 0);
  }

}
