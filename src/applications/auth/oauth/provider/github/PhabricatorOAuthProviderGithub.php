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

class PhabricatorOAuthProviderGithub extends PhabricatorOAuthProvider {

  public function getProviderKey() {
    return self::PROVIDER_GITHUB;
  }

  public function getProviderName() {
    return 'Github';
  }

  public function isProviderEnabled() {
    return PhabricatorEnv::getEnvConfig('github.auth-enabled');
  }

  public function getRedirectURI() {
    return PhabricatorEnv::getURI('/oauth/github/login/');
  }

  public function getClientID() {
    return PhabricatorEnv::getEnvConfig('github.application-id');
  }

  public function getClientSecret() {
    return PhabricatorEnv::getEnvConfig('github.application-secret');
  }

  public function getAuthURI() {
    return 'https://github.com/login/oauth/authorize';
  }

  public function getTokenURI() {
    return 'https://github.com/login/oauth/access_token';
  }

  public function getUserInfoURI() {
    return 'https://github.com/api/v2/json/user/show';
  }

}
