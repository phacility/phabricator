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
 * @group conduit
 */
class ConduitAPIRequest {

  protected $params;
  private $user;

  public function __construct(array $params) {
    $this->params = $params;
  }

  public function getValue($key) {
    return idx($this->params, $key);
  }

  public function getAllParameters() {
    return $this->params;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  /**
   * Retrieve the authentic identity of the user making the request. If a
   * method requires authentication (the default) the user object will always
   * be available. If a method does not require authentication (i.e., overrides
   * shouldRequireAuthentication() to return false) the user object will NEVER
   * be available.
   *
   * @return PhabricatorUser Authentic user, available ONLY if the method
   *                         requires authentication.
   */
  public function getUser() {
    if (!$this->user) {
      throw new Exception(
        "You can not access the user inside the implementation of a Conduit ".
        "method which does not require authentication (as per ".
        "shouldRequireAuthentication()).");
    }
    return $this->user;
  }

}
