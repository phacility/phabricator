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

final class PhabricatorOAuthServerTestCase
  extends PhabricatorTestCase {

  public function testValidateRedirectURI() {
    static $map = array(
      'http://www.google.com'              => true,
      'http://www.google.com/'             => true,
      'http://www.google.com/auth'         => true,
      'www.google.com'                     => false,
      'http://www.google.com/auth#invalid' => false
    );
    $server = new PhabricatorOAuthServer();
    foreach ($map as $input => $expected) {
      $uri = new PhutilURI($input);
      $result = $server->validateRedirectURI($uri);
      $this->assertEqual(
        $expected,
        $result,
        "Validation of redirect URI '{$input}'"
      );
    }
  }

  public function testValidateSecondaryRedirectURI() {
    $server      = new PhabricatorOAuthServer();
    $primary_uri = new PhutilURI('http://www.google.com');
    static $test_domain_map = array(
      'http://www.google.com'               => true,
      'http://www.google.com/'              => true,
      'http://www.google.com/auth'          => true,
      'http://www.google.com/?auth'         => true,
      'www.google.com'                      => false,
      'http://www.google.com/auth#invalid'  => false,
      'http://www.example.com'              => false
    );
    foreach ($test_domain_map as $input => $expected) {
      $uri = new PhutilURI($input);
      $this->assertEqual(
        $expected,
        $server->validateSecondaryRedirectURI($uri, $primary_uri),
        "Validation of redirect URI '{$input}' ".
        "relative to '{$primary_uri}'"
      );
    }

    $primary_uri = new PhutilURI('http://www.google.com/?auth');
    static $test_query_map = array(
      'http://www.google.com'               => false,
      'http://www.google.com/'              => false,
      'http://www.google.com/auth'          => false,
      'http://www.google.com/?auth'         => true,
      'http://www.google.com/?auth&stuff'   => true,
      'http://www.google.com/?stuff'        => false,
    );
    foreach ($test_query_map as $input => $expected) {
      $uri = new PhutilURI($input);
      $this->assertEqual(
        $expected,
        $server->validateSecondaryRedirectURI($uri, $primary_uri),
        "Validation of secondary redirect URI '{$input}' ".
        "relative to '{$primary_uri}'"
      );
    }

  }

}
