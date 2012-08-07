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

final class LiskIsolationTestDAO extends LiskDAO {

  protected $name;
  protected $phid;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('TISO');
  }

  public function establishLiveConnection($mode) {
    throw new LiskIsolationTestDAOException(
      "Isolation failure! DAO is attempting to connect to an external ".
      "resource!");
  }

  public function getConnectionNamespace() {
    return 'test';
  }

  public function getTableName() {
    return 'test';
  }

}
