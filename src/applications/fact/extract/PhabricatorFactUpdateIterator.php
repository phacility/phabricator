<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Iterate over objects by update time in a stable way. This iterator only works
 * for "normal" Lisk objects: objects with an autoincrement ID and a
 * dateModified column.
 */
final class PhabricatorFactUpdateIterator extends PhutilBufferedIterator {

  private $cursor;
  private $object;
  private $position;
  private $ignoreUpdatesDuration = 15;

  public function __construct(LiskDAO $object) {
    $this->object = $object;
    $this->position = '0:0';
  }

  public function setPosition($position) {
    $this->position = $position;
    return $this;
  }

  protected function didRewind() {
    $this->cursor = $this->position;
  }

  protected function getCursorFromObject($object) {
    return $object->getDateModified().':'.$object->getID();
  }

  public function key() {
    return $this->getCursorFromObject($this->current());
  }

  protected function loadPage() {
    list($after_epoch, $after_id) = explode(':', $this->cursor);

    // NOTE: We ignore recent updates because once we process an update we'll
    // never process rows behind it again. We need to read only rows which
    // we're sure no new rows will be inserted behind. If we read a row that
    // was updated on the current second, another update later on in this second
    // could affect an object with a lower ID, and we'd skip that update. To
    // avoid this, just ignore any rows which have been updated in the last few
    // seconds. This also reduces the amount of work we need to do if an object
    // is repeatedly updated; we will just look at the end state without
    // processing the intermediate states. Finally, this gives us reasonable
    // protections against clock skew between the machine the daemon is running
    // on and any machines performing writes.

    $page = $this->object->loadAllWhere(
      '((dateModified > %d) OR (dateModified = %d AND id > %d))
        AND (dateModified < %d - %d)
        ORDER BY dateModified ASC, id ASC LIMIT %d',
      $after_epoch,
      $after_epoch,
      $after_id,
      time(),
      $this->ignoreUpdatesDuration,
      $this->getPageSize());

    if ($page) {
      $this->cursor = $this->getCursorFromObject(end($page));
    }

    return $page;
  }

}
