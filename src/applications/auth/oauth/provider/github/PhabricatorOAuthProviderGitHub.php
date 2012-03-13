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

final class PhabricatorOAuthProviderGitHub extends PhabricatorOAuthProvider {

  private $userData;

  public function getProviderKey() {
    return self::PROVIDER_GITHUB;
  }

  public function getProviderName() {
    return 'GitHub';
  }

  public function isProviderEnabled() {
    return PhabricatorEnv::getEnvConfig('github.auth-enabled');
  }

  public function isProviderLinkPermanent() {
    return PhabricatorEnv::getEnvConfig('github.auth-permanent');
  }

  public function isProviderRegistrationEnabled() {
    return PhabricatorEnv::getEnvConfig('github.registration-enabled');
  }

  public function getClientID() {
    return PhabricatorEnv::getEnvConfig('github.application-id');
  }

  public function renderGetClientIDHelp() {
    return null;
  }

  public function getClientSecret() {
    return PhabricatorEnv::getEnvConfig('github.application-secret');
  }

  public function renderGetClientSecretHelp() {
    return null;
  }

  public function getAuthURI() {
    return 'https://github.com/login/oauth/authorize';
  }

  public function getTokenURI() {
    return 'https://github.com/login/oauth/access_token';
  }

  public function getTestURIs() {
    return array(
      'http://github.com',
    );
  }

  public function getUserInfoURI() {
    return 'https://github.com/api/v2/json/user/show';
  }

  public function getMinimumScope() {
    return null;
  }

  public function setUserData($data) {
    $this->userData = idx(json_decode($data, true), 'user');
    return $this;
  }

  public function retrieveUserID() {
    return $this->userData['id'];
  }

  public function retrieveUserEmail() {
    return idx($this->userData, 'email');
  }

  public function retrieveUserAccountName() {
    return $this->userData['login'];
  }

  public function retrieveUserProfileImage() {
    $id = $this->userData['gravatar_id'];
    if ($id) {
      $uri = 'http://www.gravatar.com/avatar/'.$id.'?s=50';
      return @file_get_contents($uri);
    }
    return null;
  }

  public function retrieveUserAccountURI() {
    $username = $this->retrieveUserAccountName();
    if ($username) {
      return 'https://github.com/'.$username;
    }
    return null;
  }

  public function retrieveUserRealName() {
    return idx($this->userData, 'name');
  }

}
