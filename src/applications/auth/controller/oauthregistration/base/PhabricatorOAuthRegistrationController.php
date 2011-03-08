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

abstract class PhabricatorOAuthRegistrationController
  extends PhabricatorAuthController {

  private $oauthProvider;
  private $oauthInfo;
  private $oauthState;

  final public function setOAuthInfo($info) {
    $this->oauthInfo = $info;
    return $this;
  }

  final public function getOAuthInfo() {
    return $this->oauthInfo;
  }

  final public function setOAuthProvider($provider) {
    $this->oauthProvider = $provider;
    return $this;
  }

  final public function getOAuthProvider() {
    return $this->oauthProvider;
  }

  final public function setOAuthState($state) {
    $this->oauthState = $state;
    return $this;
  }

  final public function getOAuthState() {
    return $this->oauthState;
  }

}
