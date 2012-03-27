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

final class PhabricatorFlag extends PhabricatorFlagDAO {

  protected $ownerPHID;
  protected $type;
  protected $objectPHID;
  protected $reasonPHID;
  protected $color = PhabricatorFlagColor::COLOR_BLUE;
  protected $note;

  private $handle = false;
  private $object = false;

  public function getObject() {
    if ($this->object === false) {
      throw new Exception('Call attachObject() before getObject()!');
    }
    return $this->object;
  }

  public function attachObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getHandle() {
    if ($this->handle === false) {
      throw new Exception('Call attachHandle() before getHandle()!');
    }
    return $this->handle;
  }

  public function attachHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

}
