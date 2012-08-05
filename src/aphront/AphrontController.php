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
 * @group aphront
 */
abstract class AphrontController {

  private $request;
  private $currentApplication;

  public function willBeginExecution() {
    return;
  }

  public function willProcessRequest(array $uri_data) {
    return;
  }

  abstract public function processRequest();

  final public function __construct(AphrontRequest $request) {
    $this->request = $request;
  }

  final public function getRequest() {
    return $this->request;
  }

  final public function delegateToController(AphrontController $controller) {
    return $controller->processRequest();
  }


  final public function setCurrentApplication(
    PhabricatorApplication $current_application) {

    $this->currentApplication = $current_application;
    return $this;
  }

  final public function getCurrentApplication() {
    return $this->currentApplication;
  }

}
