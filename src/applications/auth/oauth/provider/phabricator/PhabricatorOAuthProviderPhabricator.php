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

final class PhabricatorOAuthProviderPhabricator
extends PhabricatorOAuthProvider {
  private $userData;

  public function getExtraAuthParameters() {
    return array(
      'response_type' => 'code',
    );
  }

  public function getExtraTokenParameters() {
    return array(
      'grant_type' => 'authorization_code',
    );

  }
  public function decodeTokenResponse($response) {
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
      throw new Exception('Invalid token response.');
    }
    return $decoded;
  }

  public function getProviderKey() {
    return self::PROVIDER_PHABRICATOR;
  }

  public function getProviderName() {
    return 'Phabricator';
  }

  public function isProviderEnabled() {
    return PhabricatorEnv::getEnvConfig('phabricator.auth-enabled');
  }

  public function isProviderLinkPermanent() {
    return PhabricatorEnv::getEnvConfig('phabricator.auth-permanent');
  }

  public function isProviderRegistrationEnabled() {
    return PhabricatorEnv::getEnvConfig('phabricator.registration-enabled');
  }

  public function getClientID() {
    return PhabricatorEnv::getEnvConfig('phabricator.application-id');
  }

  public function renderGetClientIDHelp() {
    return null;
  }

  public function getClientSecret() {
    return PhabricatorEnv::getEnvConfig('phabricator.application-secret');
  }

  public function renderGetClientSecretHelp() {
    return null;
  }

  public function getAuthURI() {
    return $this->getURI('/oauthserver/auth/');
  }

  public function getTestURIs() {
    return array(
      $this->getURI('/'),
      $this->getURI('/api/user.whoami/')
    );
  }

  public function getTokenURI() {
    return $this->getURI('/oauthserver/token/');
  }

  public function getUserInfoURI() {
    return $this->getURI('/api/user.whoami');
  }

  public function getMinimumScope() {
    return 'whoami';
  }

  public function setUserData($data) {
    // need to strip the javascript shield from conduit
    $data = substr($data, 8);
    $data = json_decode($data, true);
    if (!is_array($data)) {
      throw new Exception('Invalid user data.');
    }
    $this->userData = $data['result'];
    return $this;
  }

  public function retrieveUserID() {
    return $this->userData['phid'];
  }

  public function retrieveUserEmail() {
    return $this->userData['email'];
  }

  public function retrieveUserAccountName() {
    return $this->userData['userName'];
  }

  public function retrieveUserProfileImage() {
    $uri = $this->userData['image'];
    return @file_get_contents($uri);
  }

  public function retrieveUserAccountURI() {
    return $this->userData['uri'];
  }

  public function retrieveUserRealName() {
    return $this->userData['realName'];
  }

  private function getURI($path) {
    return
      rtrim(PhabricatorEnv::getEnvConfig('phabricator.oauth-uri'), '/') .
      $path;
  }
}
