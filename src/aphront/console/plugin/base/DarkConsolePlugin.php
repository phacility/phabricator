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
 * @group console
 */
abstract class DarkConsolePlugin {

  private $data;
  private $request;
  private $core;

  abstract public function getName();
  abstract public function getDescription();
  abstract public function render();

  public function __construct() {

  }

  public function setConsoleCore(DarkConsoleCore $core) {
    $this->core = $core;
    return $this;
  }

  public function getConsoleCore() {
    return $this->core;
  }

  public function generateData() {
    return null;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function getRequestURI() {
    return $this->getRequest()->getRequestURI();
  }

  public function isPermanent() {
    return false;
  }

  public function shouldStartup() {
    return true;
  }

  public function didStartup() {
    return null;
  }

  public function willShutdown() {
    return null;
  }

  public function didShutdown() {
    return null;
  }

  public function processRequest() {
    return null;
  }

}
