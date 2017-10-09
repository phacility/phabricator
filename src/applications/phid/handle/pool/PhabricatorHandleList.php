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
  private $count;
  private $handles;
  private $cursor;
  private $map;

  public function setHandlePool(PhabricatorHandlePool $pool) {
    $this->handlePool = $pool;
    return $this;
  }

  public function setPHIDs(array $phids) {
    $this->phids = $phids;
    $this->count = count($phids);
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


  /**
   * Get a handle from this list if it exists.
   *
   * This has similar semantics to @{function:idx}.
   */
  public function getHandleIfExists($phid, $default = null) {
    if ($this->handles === null) {
      $this->loadHandles();
    }

    return idx($this->handles, $phid, $default);
  }


  /**
   * Create a new list with a subset of the PHIDs in this list.
   */
  public function newSublist(array $phids) {
    foreach ($phids as $phid) {
      if (!isset($this[$phid])) {
        throw new Exception(
          pht(
            'Trying to create a new sublist of an existing handle list, '.
            'but PHID "%s" does not appear in the parent list.',
            $phid));
      }
    }

    return $this->handlePool->newHandleList($phids);
  }


/* -(  Rendering  )---------------------------------------------------------- */


  /**
   * Return a @{class:PHUIHandleListView} which can render the handles in
   * this list.
   */
  public function renderList() {
    return id(new PHUIHandleListView())
      ->setHandleList($this);
  }


  /**
   * Return a @{class:PHUIHandleView} which can render a specific handle.
   */
  public function renderHandle($phid) {
    if (!isset($this[$phid])) {
      throw new Exception(
        pht('Trying to render a handle which does not exist!'));
    }

    return id(new PHUIHandleView())
      ->setHandleList($this)
      ->setHandlePHID($phid);
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
    return ($this->cursor < $this->count);
  }


/* -(  ArrayAccess  )-------------------------------------------------------- */


  public function offsetExists($offset) {
    // NOTE: We're intentionally not loading handles here so that isset()
    // checks do not trigger fetches. This gives us better bulk loading
    // behavior, particularly when invoked through methods like renderHandle().

    if ($this->map === null) {
      $this->map = array_fill_keys($this->phids, true);
    }

    return isset($this->map[$offset]);
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
        'Trying to mutate a %s, but this is not permitted; '.
        'handle lists are immutable.',
        __CLASS__));
  }


/* -(  Countable  )---------------------------------------------------------- */


  public function count() {
    return $this->count;
  }

}
