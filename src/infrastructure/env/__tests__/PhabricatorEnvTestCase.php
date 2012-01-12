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

final class PhabricatorEnvTestCase extends PhabricatorTestCase {

  public function testLocalWebResource() {
    $map = array(
      '/'                     => true,
      '/D123'                 => true,
      '/path/to/something/'   => true,
      "/path/to/\nHeader: x"  => false,
      'http://evil.com/'      => false,
      '//evil.com/evil/'      => false,
      'javascript:lol'        => false,
      ''                      => false,
      null                    => false,
    );

    foreach ($map as $uri => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorEnv::isValidLocalWebResource($uri),
        "Valid local resource: {$uri}");
    }
  }

  public function testRemoteWebResource() {
    $map = array(
      'http://example.com/'   => true,
      'derp://example.com/'   => false,
      'javascript:alert(1)'   => false,
    );

    foreach ($map as $uri => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorEnv::isValidRemoteWebResource($uri),
        "Valid remote resource: {$uri}");
    }
  }
}
