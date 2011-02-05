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


/**
 * @group aphront
 */
class AphrontRequest {

  const TYPE_AJAX = '__ajax__';
  const TYPE_FORM = '__form__';

  private $host;
  private $path;
  private $requestData;
  private $user;
  private $env;
  private $applicationConfiguration;

  final public function __construct($host, $path) {
    $this->host = $host;
    $this->path = $path;
  }

  final public function setApplicationConfiguration(
    $application_configuration) {
    $this->applicationConfiguration = $application_configuration;
    return $this;
  }

  final public function getApplicationConfiguration() {
    return $this->applicationConfiguration;
  }

  final public function setRequestData(array $request_data) {
    $this->requestData = $request_data;
    return $this;
  }

  final public function getPath() {
    return $this->path;
  }

  final public function getHost() {
    return $this->host;
  }

  final public function getInt($name, $default = null) {
    if (isset($this->requestData[$name])) {
      return (int)$this->requestData[$name];
    } else {
      return $default;
    }
  }

  final public function getStr($name, $default = null) {
    if (isset($this->requestData[$name])) {
      $str = (string)$this->requestData[$name];
      // Normalize newline craziness.
      $str = str_replace(
        array("\r\n", "\r"),
        array("\n", "\n"),
        $str);
      return $str;
    } else {
      return $default;
    }
  }

  final public function getArr($name, $default = array()) {
    if (isset($this->requestData[$name]) &&
        is_array($this->requestData[$name])) {
      return $this->requestData[$name];
    } else {
      return $default;
    }
  }

  final public function getExists($name) {
    return array_key_exists($name, $this->requestData);
  }

  final public function isHTTPPost() {
    return ($_SERVER['REQUEST_METHOD'] == 'POST');
  }

  final public function isAjax() {
    return $this->getExists(self::TYPE_AJAX);
  }

  final public function isFormPost() {
    return $this->getExists(self::TYPE_FORM) &&
           $this->isHTTPPost() &&
           $this->getUser()->validateCSRFToken($this->getStr('__csrf__'));
  }

  final public function getCookie($name, $default = null) {
    return idx($_COOKIE, $name, $default);
  }

  final public function clearCookie($name) {
    $this->setCookie($name, '', time() - (60 * 60 * 24 * 30));
  }

  final public function setCookie($name, $value, $expire = null) {
    if ($expire === null) {
      $expire = time() + (60 * 60 * 24 * 365 * 5);
    }
    setcookie(
      $name,
      $value,
      $expire,
      $path = '/',
      $domain = '',
      $secure = false,
      $http_only = true);
  }

  final public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  final public function getUser() {
    return $this->user;
  }

}
