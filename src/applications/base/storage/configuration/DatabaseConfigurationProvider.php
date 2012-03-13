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

/**
 * TODO: Can we final this?
 */
class DatabaseConfigurationProvider {
  private $dao;
  private $mode;

  public function __construct(LiskDAO $dao, $mode) {
    $this->dao = $dao;
    $this->mode = $mode;
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
    return 'phabricator_'.$this->getDao()->getApplicationName();
  }

  final protected function getDao() {
    return $this->dao;
  }

  final protected function getMode() {
    return $this->mode;
  }

  public static function getConfiguration() {
    // Get DB info. Note that we are using a dummy PhabricatorUser object in
    // creating the DatabaseConfigurationProvider, which is not used at all.
    $conf_provider = PhabricatorEnv::getEnvConfig(
      'mysql.configuration_provider', 'DatabaseConfigurationProvider');
    PhutilSymbolLoader::loadClass($conf_provider);
    $conf = newv($conf_provider, array(new PhabricatorUser(), 'r'));
    return $conf;
  }

}
