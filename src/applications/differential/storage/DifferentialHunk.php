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

  public function getAddedLines() {
    return $this->makeContent($include = '+');
  }

  public function makeNewFile() {
    return implode('', $this->makeContent($include = ' +'));
  }

  public function makeOldFile() {
    return implode('', $this->makeContent($include = ' -'));
  }

  public function makeChanges() {
    return implode('', $this->makeContent($include = '-+'));
  }

  final private function makeContent($include) {
    $results = array();
    $lines = explode("\n", $this->changes);

    // NOTE: To determine whether the recomposed file should have a trailing
    // newline, we look for a "\ No newline at end of file" line which appears
    // after a line which we don't exclude. For example, if we're constructing
    // the "new" side of a diff (excluding "-"), we want to ignore this one:
    //
    //    - x
    //    \ No newline at end of file
    //    + x
    //
    // ...since it's talking about the "old" side of the diff, but interpret
    // this as meaning we should omit the newline:
    //
    //    - x
    //    + x
    //    \ No newline at end of file

    $n = (strpos($include, '+') !== false ?
      $this->newOffset :
      $this->oldOffset);
    $use_next_newline = false;
    foreach ($lines as $line) {
      if (!isset($line[0])) {
        continue;
      }

      if ($line[0] == '\\') {
        if ($use_next_newline) {
          $results[last_key($results)] = rtrim(end($results), "\n");
        }
      } else if (strpos($include, $line[0]) === false) {
        $use_next_newline = false;
      } else {
        $use_next_newline = true;
        $results[$n] = substr($line, 1)."\n";
      }

      if ($line[0] == ' ' || strpos($include, $line[0]) !== false) {
        $n++;
      }
    }

    return $results;
  }

}
