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

final class LiskFixtureTestCase extends PhabricatorTestCase {

  public function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testTransactionalIsolation1of2() {
    // NOTE: These tests are verifying that data is destroyed between tests.
    // If the user from either test persists, the other test will fail.
    $this->assertEqual(
      0,
      count(id(new PhabricatorUser())->loadAll()));

    id(new PhabricatorUser())
      ->setUserName('alincoln')
      ->setRealName('Abraham Lincoln')
      ->setEmail('alincoln@example.com')
      ->save();
  }

  public function testTransactionalIsolation2of2() {
    $this->assertEqual(
      0,
      count(id(new PhabricatorUser())->loadAll()));

    id(new PhabricatorUser())
      ->setUserName('ugrant')
      ->setRealName('Ulysses S. Grant')
      ->setEmail('ugrant@example.com')
      ->save();
  }

  public function testFixturesBasicallyWork() {
    $this->assertEqual(
      0,
      count(id(new PhabricatorUser())->loadAll()));

    id(new PhabricatorUser())
      ->setUserName('gwashington')
      ->setRealName('George Washington')
      ->setEmail('gwashington@example.com')
      ->save();

    $this->assertEqual(
      1,
      count(id(new PhabricatorUser())->loadAll()));
  }

}
