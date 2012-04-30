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

final class PhabricatorStoragePatch {

  private $key;
  private $fullKey;
  private $name;
  private $type;
  private $after;
  private $legacy;

  public function __construct(array $dict) {
    $this->key      = $dict['key'];
    $this->type     = $dict['type'];
    $this->fullKey  = $dict['fullKey'];
    $this->legacy   = $dict['legacy'];
    $this->name     = $dict['name'];
    $this->after    = $dict['after'];
  }

  public function getLegacy() {
    return $this->legacy;
  }

  public function getAfter() {
    return $this->after;
  }

  public function getType() {
    return $this->type;
  }

  public function getName() {
    return $this->name;
  }

  public function getFullKey() {
    return $this->fullKey;
  }

  public function getKey() {
    return $this->key;
  }

}
