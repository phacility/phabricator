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

final class DifferentialHunk extends DifferentialDAO {

  protected $changesetID;
  protected $changes;
  protected $oldOffset;
  protected $oldLen;
  protected $newOffset;
  protected $newLen;

  public function makeNewFile() {
    return $this->makeContent($exclude = '-');
  }

  public function makeOldFile() {
    return $this->makeContent($exclude = '+');
  }

  public function makeChanges() {
    return $this->makeContent($exclude = ' ');
  }

  final private function makeContent($exclude) {
    $results = array();
    $lines = explode("\n", $this->changes);
    foreach ($lines as $line) {
      if (isset($line[0]) && $line[0] == $exclude) {
        continue;
      }
      $results[] = substr($line, 1);
    }
    return implode("\n", $results);
  }

}
