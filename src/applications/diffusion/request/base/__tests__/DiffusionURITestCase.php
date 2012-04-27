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

final class DiffusionURITestCase extends ArcanistPhutilTestCase {

  public function testBlobDecode() {
    $map = array(
      // This is a basic blob.
      'branch/path.ext;abc$3' => array(
        'branch'  => 'branch',
        'path'    => 'path.ext',
        'commit'  => 'abc',
        'line'    => '3',
      ),
      'branch/path.ext$3' => array(
        'branch'  => 'branch',
        'path'    => 'path.ext',
        'line'    => '3',
      ),
      'branch/money;;/$$100'  => array(
        'branch'  => 'branch',
        'path'    => 'money;/$100',
      ),
      'a%252Fb/' => array(
        'branch'  => 'a/b',
      ),
      'branch/path/;Version-1_0_0' => array(
        'branch' => 'branch',
        'path'   => 'path/',
        'commit' => 'Version-1_0_0',
      ),
      'branch/path/;$$moneytag$$' => array(
        'branch' => 'branch',
        'path'   => 'path/',
        'commit' => '$moneytag$',
      ),
      'branch/path/semicolon;;;;;$$;;semicolon;;$$$$$100' => array(
        'branch' => 'branch',
        'path'   => 'path/semicolon;;',
        'commit' => '$;;semicolon;;$$',
        'line'   => '100',
      ),
    );

    foreach ($map as $input => $expect) {

      // Simulate decode effect of the webserver.
      $input = rawurldecode($input);

      $expect = $expect + array(
        'branch' => null,
        'path'   => null,
        'commit' => null,
        'line'   => null,
      );
      $expect = array_select_keys(
        $expect,
        array('branch', 'path', 'commit', 'line'));

      $actual = $this->parseBlob($input);

      $this->assertEqual(
        $expect,
        $actual,
        "Parsing '{$input}'");
    }
  }

  public function testBlobDecodeFail() {
    $this->tryTestCaseMap(
      array(
        'branch/path/../../../secrets/secrets.key' => false,
      ),
      array($this, 'parseBlob'));
  }

  public function parseBlob($blob) {
    return DiffusionRequest::parseRequestBlob(
      $blob,
      $supports_branches = true);
  }

  public function testURIGeneration() {
    $map = array(
      '/diffusion/A/browse/branch/path.ext;abc$1' => array(
        'action'    => 'browse',
        'callsign'  => 'A',
        'branch'    => 'branch',
        'path'      => 'path.ext',
        'commit'    => 'abc',
        'line'      => '1',
      ),
      '/diffusion/A/browse/a%252Fb/path.ext' => array(
        'action'    => 'browse',
        'callsign'  => 'A',
        'branch'    => 'a/b',
        'path'      => 'path.ext',
      ),
      '/diffusion/A/browse/%2B/%20%21' => array(
        'action'    => 'browse',
        'callsign'  => 'A',
        'path'      => '+/ !',
      ),
      '/diffusion/A/browse/money/%24%24100$2' => array(
        'action'    => 'browse',
        'callsign'  => 'A',
        'path'      => 'money/$100',
        'line'      => '2',
      ),
      '/diffusion/A/browse/path/to/file.ext?view=things' => array(
        'action'    => 'browse',
        'callsign'  => 'A',
        'path'      => 'path/to/file.ext',
        'params'    => array(
          'view' => 'things',
        ),
      ),
      '/diffusion/A/repository/master/' => array(
        'action'    => 'branch',
        'callsign'  => 'A',
        'branch'    => 'master',
      ),
      'path/to/file.ext;abc' => array(
        'action'    => 'rendering-ref',
        'path'      => 'path/to/file.ext',
        'commit'    => 'abc',
      ),
    );

    foreach ($map as $expect => $input) {
      $actual = DiffusionRequest::generateDiffusionURI($input);
      $this->assertEqual(
        $expect,
        (string)$actual);
    }
  }

}
