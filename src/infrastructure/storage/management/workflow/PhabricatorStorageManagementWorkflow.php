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

abstract class PhabricatorStorageManagementWorkflow
  extends PhutilArgumentWorkflow {

  private $patches;
  private $api;

  public function setPatches(array $patches) {
    assert_instances_of($patches, 'PhabricatorStoragePatch');
    $this->patches = $patches;
    return $this;
  }

  public function getPatches() {
    return $this->patches;
  }

  final public function setAPI(PhabricatorStorageManagementAPI $api) {
    $this->api = $api;
    return $this;
  }

  final public function getAPI() {
    return $this->api;
  }

  public function isExecutable() {
    return true;
  }

}
