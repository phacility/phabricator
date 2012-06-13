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

final class PhabricatorLDAPProvider {
  private $userData;
  private $connection;

  public function __construct() {

  }

  public function __destruct() {
    if (isset($this->connection)) {
      ldap_unbind($this->connection);
    }
  }

  public function isProviderEnabled() {
    return PhabricatorEnv::getEnvConfig('ldap.auth-enabled');
  }

  public function getHostname() {
    return PhabricatorEnv::getEnvConfig('ldap.hostname');
  }

  public function getBaseDN() {
    return PhabricatorEnv::getEnvConfig('ldap.base_dn');
  }

  public function getSearchAttribute() {
    return PhabricatorEnv::getEnvConfig('ldap.search_attribute');
  }

  public function getLDAPVersion() {
    return PhabricatorEnv::getEnvConfig('ldap.version');
  }

  public function retrieveUserEmail() {
    return $this->userData['mail'][0];
  }

  public function retrieveUserRealName() {
    $name_attributes = PhabricatorEnv::getEnvConfig(
        'ldap.real_name_attributes');

    $real_name = '';
    if (is_array($name_attributes)) {
      foreach ($name_attributes AS $attribute) {
        if (isset($this->userData[$attribute][0])) {
          $real_name .= $this->userData[$attribute][0] . ' ';
        }
      }

      trim($real_name);
    } else if (isset($this->userData[$name_attributes][0])) {
      $real_name = $this->userData[$name_attributes][0];
    }

    if ($real_name == '') {
      return null;
    }

    return $real_name;
  }

  public function retrieveUsername() {
    return $this->userData[$this->getSearchAttribute()][0];
  }

  public function getConnection() {
    if (!isset($this->connection)) {
      $this->connection = ldap_connect($this->getHostname());

      if (!$this->connection) {
        throw new Exception('Could not connect to LDAP host at ' .
          $this->getHostname());
      }

      ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION,
        $this->getLDAPVersion());
    }

    return $this->connection;
  }

  public function getUserData() {
    return $this->userData;
  }

  public function auth($username, $password) {
    if (strlen(trim($username)) == 0 || strlen(trim($password)) == 0) {
      throw new Exception('Username and/or password can not be empty');
    }

    $result = ldap_bind($this->getConnection(),
              $this->getSearchAttribute() . '=' . $username . ',' .
              $this->getBaseDN(),
              $password);

    if (!$result) {
      throw new Exception('Bad username/password.');
    }

    $this->userData = $this->getUser($username);
    return $this->userData;
  }

  private function getUser($username) {
    $result = ldap_search($this->getConnection(), $this->getBaseDN(),
              $this->getSearchAttribute() . '=' . $username);

    if (!$result) {
      throw new Exception('Search failed. Please check your LDAP and HTTP '.
        'logs for more information.');
    }

    $entries = ldap_get_entries($this->getConnection(), $result);

    if ($entries === false) {
      throw new Exception('Could not get entries');
    }

    if ($entries['count'] > 1) {
      throw new Exception('Found more then one user with this ' .
        $this->getSearchAttribute());
    }

    if ($entries['count'] == 0) {
      throw new Exception('Could not find user');
    }

    return $entries[0];
  }
}
