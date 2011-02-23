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

class PhabricatorOAuthProviderFacebook extends PhabricatorOAuthProvider {

  public function getProviderKey() {
    return self::PROVIDER_FACEBOOK;
  }

  public function getProviderName() {
    return 'Facebook';
  }

  public function isProviderEnabled() {
    return PhabricatorEnv::getEnvConfig('facebook.auth-enabled');
  }

  public function getRedirectURI() {
    return PhabricatorEnv::getURI('/oauth/facebook/login/');
  }

  public function getClientID() {
    return PhabricatorEnv::getEnvConfig('facebook.application-id');
  }

  public function getClientSecret() {
    return PhabricatorEnv::getEnvConfig('facebook.application-secret');
  }

  public function getAuthURI() {
    return 'https://www.facebook.com/dialog/oauth';
  }

  public function getTokenURI() {
    return 'https://graph.facebook.com/oauth/access_token';
  }

  public function getUserInfoURI() {
    return 'https://graph.facebook.com/me';
  }

  public function getMinimumScope() {
    return 'email';
  }

}
