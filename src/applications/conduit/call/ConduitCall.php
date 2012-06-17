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
 * Run a conduit method in-process, without requiring HTTP requests. Usage:
 *
 *   $call = new ConduitCall('method.name', array('param' => 'value'));
 *   $call->setUser($user);
 *   $result = $call->execute();
 *
 */
final class ConduitCall {

  private $method;
  private $params;
  private $request;
  private $user;

  public function __construct($method, array $params) {
    $this->method = $method;
    $this->params = $params;
    $this->handler = $this->buildMethodHandler($method);
    $this->request = new ConduitAPIRequest($params);
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function shouldRequireAuthentication() {
    return $this->handler->shouldRequireAuthentication();
  }

  public function shouldAllowUnguardedWrites() {
    return $this->handler->shouldAllowUnguardedWrites();
  }

  public function getRequiredScope() {
    return $this->handler->getRequiredScope();
  }

  public function getErrorDescription($code) {
    return $this->handler->getErrorDescription($code);
  }

  public function execute() {
    if (!$this->getUser()) {
      if ($this->shouldRequireAuthentication()) {
        throw new ConduitException("ERR-INVALID-AUTH");
      }
    } else {
      $this->request->setUser($this->getUser());
    }

    return $this->handler->executeMethod($this->request);
  }

  protected function buildMethodHandler($method) {
    $method_class = ConduitAPIMethod::getClassNameFromAPIMethodName($method);

    // Test if the method exists.
    $ok = false;
    try {
      $ok = class_exists($method_class);
    } catch (Exception $ex) {
      // Discard, we provide a more specific exception below.
    }
    if (!$ok) {
      throw new Exception(
        "Conduit method '{$method}' does not exist.");
    }

    $class_info = new ReflectionClass($method_class);
    if ($class_info->isAbstract()) {
      throw new Exception(
        "Method '{$method}' is not valid; the implementation is an abstract ".
        "base class.");
    }

    return newv($method_class, array());
  }


}
