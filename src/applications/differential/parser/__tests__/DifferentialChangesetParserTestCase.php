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

final class DifferentialChangesetParserTestCase extends ArcanistPhutilTestCase {

  public function testDiffChangesets() {
    $hunk = new DifferentialHunk();
    $hunk->setChanges("+a\n b\n-c");
    $hunk->setNewOffset(1);
    $hunk->setNewLen(2);
    $left = new DifferentialChangeset();
    $left->attachHunks(array($hunk));

    $tests = array(
      "+a\n b\n-c" => array(array(), array()),
      "+a\n x\n-c" => array(array(), array()),
      "+aa\n b\n-c" => array(array(1), array(11)),
      " b\n-c" => array(array(1), array()),
      "+a\n b\n c" => array(array(), array(13)),
      "+a\n x\n c" => array(array(), array(13)),
    );

    foreach ($tests as $changes => $expected) {
      $hunk = new DifferentialHunk();
      $hunk->setChanges($changes);
      $hunk->setNewOffset(11);
      $hunk->setNewLen(3);
      $right = new DifferentialChangeset();
      $right->attachHunks(array($hunk));

      $parser = new DifferentialChangesetParser();
      $parser->setOriginals($left, $right);
      $this->assertEqual($expected, $parser->diffOriginals(), $changes);
    }
  }

}
