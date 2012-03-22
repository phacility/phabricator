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


    $use_next_newline = false;
    $has_newline = true;
    foreach ($lines as $line) {
      if (isset($line[0])) {
        if ($line[0] == $exclude) {
          $use_next_newline = false;
          continue;
        }
        if ($line[0] == '\\') {
          if ($use_next_newline) {
            $has_newline = false;
          }
          continue;
        }
      }
      $use_next_newline = true;
      $results[] = substr($line, 1);
    }

    $possible_newline = '';
    if ($has_newline) {
      $possible_newline = "\n";
    }

    return implode("\n", $results).$possible_newline;
  }

}
