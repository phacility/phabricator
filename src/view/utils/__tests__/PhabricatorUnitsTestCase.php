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

final class PhabricatorUnitsTestCase extends PhabricatorTestCase {

  public function testByteFormatting() {
    $tests = array(
      1               => '1 B',
      1000            => '1 KB',
      1000000         => '1 MB',
      10000000        => '10 MB',
      100000000       => '100 MB',
      1000000000      => '1 GB',
      1000000000000   => '1 TB',
      999             => '999 B',
    );

    foreach ($tests as $input => $expect) {
      $this->assertEqual(
        $expect,
        phabricator_format_bytes($input),
        'phabricator_format_bytes('.$input.')');
    }
  }

  public function testByteParsing() {
    $tests = array(
      '1'             => 1,
      '1k'            => 1000,
      '1K'            => 1000,
      '1kB'           => 1000,
      '1Kb'           => 1000,
      '1KB'           => 1000,
      '1MB'           => 1000000,
      '1GB'           => 1000000000,
      '1TB'           => 1000000000000,
      '1.5M'          => 1500000,
      '1 000'         => 1000,
      '1,234.56 KB'   => 1234560,
    );

    foreach ($tests as $input => $expect) {
      $this->assertEqual(
        $expect,
        phabricator_parse_bytes($input),
        'phabricator_parse_bytes('.$input.')');
    }
  }

}
