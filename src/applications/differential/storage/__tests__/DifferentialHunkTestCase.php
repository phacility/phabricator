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

final class DifferentialHunkTestCase extends ArcanistPhutilTestCase {

  public function testMakeChanges() {
    $root = dirname(__FILE__).'/hunk/';

    $hunk = new DifferentialHunk();
    $hunk->setChanges(Filesystem::readFile($root.'basic.diff'));
    $hunk->setOldOffset(1);
    $hunk->setNewOffset(11);

    $old = Filesystem::readFile($root.'old.txt');
    $this->assertEqual($old, $hunk->makeOldFile());

    $new = Filesystem::readFile($root.'new.txt');
    $this->assertEqual($new, $hunk->makeNewFile());

    $added = array(
      12 => "1 quack\n",
      13 => "1 quack\n",
      16 => "5 drake\n",
    );
    $this->assertEqual($added, $hunk->getAddedLines());

    $hunk = new DifferentialHunk();
    $hunk->setChanges(Filesystem::readFile($root.'newline.diff'));
    $hunk->setOldOffset(1);
    $hunk->setNewOffset(11);

    $this->assertEqual("a\n", $hunk->makeOldFile());
    $this->assertEqual("a", $hunk->makeNewFile());
    $this->assertEqual(array(11 => "a"), $hunk->getAddedLines());

  }

}
