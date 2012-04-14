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
 * Configurable test object for implementing Policy unit tests.
 */
final class PhabricatorPolicyTestObject
  implements PhabricatorPolicyInterface {

  private $capabilities           = array();
  private $policies               = array();
  private $automaticCapabilities  = array();

  public function getCapabilities() {
    return $this->capabilities;
  }

  public function getPolicy($capability) {
    return idx($this->policies, $capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $auto = idx($this->automaticCapabilities, $capability, array());
    return idx($auto, $viewer->getPHID());
  }

  public function setCapabilities(array $capabilities) {
    $this->capabilities = $capabilities;
    return $this;
  }

  public function setPolicies(array $policy_map) {
    $this->policies = $policy_map;
    return $this;
  }

  public function setAutomaticCapabilities(array $auto_map) {
    $this->automaticCapabilities = $auto_map;
    return $this;
  }

}
