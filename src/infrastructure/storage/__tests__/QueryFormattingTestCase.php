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

final class QueryFormattingTestCase extends PhabricatorTestCase {

  public function testQueryFormatting() {
    $conn_r = id(new PhabricatorUser())->establishConnection('r');

    $this->assertEqual(
      'NULL',
      qsprintf($conn_r, '%nd', null));

    $this->assertEqual(
      '0',
      qsprintf($conn_r, '%nd', 0));

    $this->assertEqual(
      '0',
      qsprintf($conn_r, '%d', 0));

    $raised = null;
    try {
      qsprintf($conn_r, '%d', 'derp');
    } catch (Exception $ex) {
      $raised = $ex;
    }
    $this->assertEqual(
      (bool)$raised,
      true,
      'qsprintf should raise exception for invalid %d conversion.');

    $this->assertEqual(
      "'<S>'",
      qsprintf($conn_r, '%s', null));

    $this->assertEqual(
      'NULL',
      qsprintf($conn_r, '%ns', null));
  }

}
