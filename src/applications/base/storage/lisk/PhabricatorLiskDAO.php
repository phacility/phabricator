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


abstract class PhabricatorLiskDAO extends LiskDAO {

  public function establishConnection($mode) {
    $conf_provider = PhabricatorEnv::getEnvConfig(
      'mysql.configuration_provider', 'DatabaseConfigurationProvider');
    PhutilSymbolLoader::loadClass($conf_provider);
    $conf = newv($conf_provider, array($this, $mode));

    return new AphrontMySQLDatabaseConnection(
      array(
        'user'      => $conf->getUser(),
        'pass'      => $conf->getPassword(),
        'host'      => $conf->getHost(),
        'database'  => $conf->getDatabase(),
      ));
  }

  public function getTableName() {
    $str = 'phabricator';
    $len = strlen($str);

    $class = strtolower(get_class($this));
    if (!strncmp($class, $str, $len)) {
      $class = substr($class, $len);
    }
    $app = $this->getApplicationName();
    if (!strncmp($class, $app, strlen($app))) {
      $class = substr($class, strlen($app));
    }

    if (strlen($class)) {
      return $app.'_'.$class;
    } else {
      return $app;
    }
  }

  abstract public function getApplicationName();
}
