<?php

/*
 * Copyright 2011 Facebook, Inc.
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

final class DifferentialAuxiliaryField extends DifferentialDAO {

  protected $revisionPHID;
  protected $name;
  protected $value;

  public function setName($name) {
    if (strlen($name) > 32) {
      throw new Exception(
        "Tried to set name '{$name}' for a Differential auxiliary field; ".
        "auxiliary field names must be no longer than 32 characters.");
    }
    $this->name = $name;
    return $this;
  }

}
