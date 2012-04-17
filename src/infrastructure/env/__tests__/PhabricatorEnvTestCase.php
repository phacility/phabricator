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

  public function testOverrides() {
    $outer = PhabricatorEnv::beginScopedEnv();
      $outer->overrideEnvConfig('test.value', 1);
      $this->assertEqual(1, PhabricatorEnv::getEnvConfig('test.value'));

      $inner = PhabricatorEnv::beginScopedEnv();
        $inner->overrideEnvConfig('test.value', 2);
        $this->assertEqual(2, PhabricatorEnv::getEnvConfig('test.value'));
      unset($inner);

      $this->assertEqual(1, PhabricatorEnv::getEnvConfig('test.value'));
    unset($outer);
  }

  public function testOverrideOrder() {
    $outer = PhabricatorEnv::beginScopedEnv();
    $middle = PhabricatorEnv::beginScopedEnv();
    $inner = PhabricatorEnv::beginScopedEnv();

    $caught = null;
    try {
      unset($middle);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      $caught instanceof Exception,
      "Destroying a scoped environment which is not on the top of the stack ".
      "should throw.");

    $caught = null;
    try {
      unset($inner);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      $caught instanceof Exception,
      "Destroying a scoped environment which is not on the top of the stack ".
      "should throw.");

    // Although we popped the other two out-of-order, we still expect to end
    // up in the right state after handling the exceptions, so this should
    // execute without issues.
    unset($outer);
  }

}
