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

final class DefaultDatabaseConfigurationProvider
  implements DatabaseConfigurationProvider {

  private $dao;
  private $mode;
  private $namespace;

  public function __construct(
    LiskDAO $dao = null,
    $mode = 'r',
    $namespace = 'phabricator') {

    $this->dao = $dao;
    $this->mode = $mode;
    $this->namespace = $namespace;
  }

  public function getUser() {
    return PhabricatorEnv::getEnvConfig('mysql.user');
  }

  public function getPassword() {
    return PhabricatorEnv::getEnvConfig('mysql.pass');
  }

  public function getHost() {
    return PhabricatorEnv::getEnvConfig('mysql.host');
  }

  public function getDatabase() {
    if (!$this->getDao()) {
      return null;
    }
    return $this->namespace.'_'.$this->getDao()->getApplicationName();
  }

  final protected function getDao() {
    return $this->dao;
  }

}
