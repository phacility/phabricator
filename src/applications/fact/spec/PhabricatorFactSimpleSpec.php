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

final class PhabricatorFactSimpleSpec extends PhabricatorFactSpec {

  private $type;
  private $name;
  private $unit;

  public function __construct($type) {
    $this->type = $type;
  }

  public function getType() {
    return $this->type;
  }

  public function setUnit($unit) {
    $this->unit = $unit;
    return $this;
  }

  public function getUnit() {
    return $this->unit;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    if ($this->name !== null) {
      return $this->name;
    }
    return parent::getName();
  }

}
