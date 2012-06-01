<?php

/*
 * Copyright 2011 Facebook, Inc.
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

final class DifferentialRevisionIDFieldParserTestCase
  extends PhabricatorTestCase {

  public function testFieldParser() {

    $this->assertEqual(
      null,
      $this->parse('123'));

    $this->assertEqual(
      null,
      $this->parse('D123'));

    // NOTE: We expect foreign, validly-formatted URIs to be ignored.
    $this->assertEqual(
      null,
      $this->parse('http://phabricator.example.com/D123'));

    $this->assertEqual(
      123,
      $this->parse(PhabricatorEnv::getProductionURI('/D123')));

  }

  private function parse($value) {
    return DifferentialRevisionIDFieldSpecification::parseRevisionIDFromURI(
      $value);
  }

}
