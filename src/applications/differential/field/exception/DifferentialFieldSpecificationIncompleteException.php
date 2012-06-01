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

final class DifferentialFieldSpecificationIncompleteException
  extends Exception {

  public function __construct(DifferentialFieldSpecification $spec) {
    $key = $spec->getStorageKey();
    $class = get_class($spec);

    parent::__construct(
      "Differential field specification for '{$key}' (of class '{$class}') is ".
      "incompletely implemented: it claims it should appear in a context but ".
      "does not implement all the required methods for that context.");
  }

}
