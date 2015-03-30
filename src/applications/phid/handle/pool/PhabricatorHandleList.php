<?php

/**
 * A list of object handles.
 *
 * This is a convenience class which behaves like an array but makes working
 * with handles more convenient, improves their caching and batching semantics,
 * and provides some utility behavior.
 *
 * Load a handle list by calling `loadHandles()` on a `$viewer`:
 *
 *   $handles = $viewer->loadHandles($phids);
 *
 * This creates a handle list object, which behaves like an array of handles.
 * However, it benefits from the viewer's internal handle cache and performs
 * just-in-time bulk loading.
 */
final class PhabricatorHandleList
  extends Phobject
  implements
    Iterator,
    ArrayAccess,
    Countable {

  private $handlePool;
  private $phids;
  private $handles;
  private $cursor;

  public function setHandlePool(PhabricatorHandlePool $pool) {
    $this->handlePool = $pool;
    return $this;
  }

  public function setPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  private function loadHandles() {
    $this->handles = $this->handlePool->loadPHIDs($this->phids);
  }

  private function getHandle($phid) {
    if ($this->handles === null) {
      $this->loadHandles();
    }

    if (empty($this->handles[$phid])) {
      throw new Exception(
        pht(
          'Requested handle "%s" was not loaded.',
          $phid));
    }

    return $this->handles[$phid];
  }


/* -(  Iterator  )----------------------------------------------------------- */


  public function rewind() {
    $this->cursor = 0;
  }

  public function current() {
    return $this->getHandle($this->phids[$this->cursor]);
  }

  public function key() {
    return $this->phids[$this->cursor];
  }

  public function next() {
    ++$this->cursor;
  }

  public function valid() {
    return isset($this->phids[$this->cursor]);
  }


/* -(  ArrayAccess  )-------------------------------------------------------- */


  public function offsetExists($offset) {
    if ($this->handles === null) {
      $this->loadHandles();
    }
    return isset($this->handles[$offset]);
  }

  public function offsetGet($offset) {
    if ($this->handles === null) {
      $this->loadHandles();
    }
    return $this->handles[$offset];
  }

  public function offsetSet($offset, $value) {
    $this->raiseImmutableException();
  }

  public function offsetUnset($offset) {
    $this->raiseImmutableException();
  }

  private function raiseImmutableException() {
    throw new Exception(
      pht(
        'Trying to mutate a PhabricatorHandleList, but this is not permitted; '.
        'handle lists are immutable.'));
  }


/* -(  Countable  )---------------------------------------------------------- */


  public function count() {
    return count($this->phids);
  }

}
