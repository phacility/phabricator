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

final class PhabricatorOAuthProviderGoogle extends PhabricatorOAuthProvider {

  private $userData;

  public function getProviderKey() {
    return self::PROVIDER_GOOGLE;
  }

  public function getProviderName() {
    return 'Google';
  }

  public function isProviderEnabled() {
    return PhabricatorEnv::getEnvConfig('google.auth-enabled');
  }

  public function isProviderLinkPermanent() {
    return PhabricatorEnv::getEnvConfig('google.auth-permanent');
  }

  public function isProviderRegistrationEnabled() {
    return PhabricatorEnv::getEnvConfig('google.registration-enabled');
  }

  public function getClientID() {
    return PhabricatorEnv::getEnvConfig('google.application-id');
  }

  public function renderGetClientIDHelp() {
    return null;
  }

  public function getClientSecret() {
    return PhabricatorEnv::getEnvConfig('google.application-secret');
  }

  public function renderGetClientSecretHelp() {
    return null;
  }

  public function getAuthURI() {
    return 'https://accounts.google.com/o/oauth2/auth';
  }

  public function getTestURIs() {
    return array(
      'http://www.google.com'
    );
  }

  public function getTokenURI() {
    return 'https://accounts.google.com/o/oauth2/token';
  }

  public function getUserInfoURI() {
    return 'https://www.google.com/m8/feeds/contacts/default/full';
  }

  public function getMinimumScope() {
    // This is the Google contacts API, which is apparently the best way to get
    // the user ID / login / email since Google doesn't apparently have a
    // more generic "user.info" sort of call (or, if it does, I couldn't find
    // it). This is sort of terrifying since it lets Phabricator read your whole
    // address book and possibly your physical address and such, so it would
    // be really nice to find a way to restrict this scope to something less
    // crazily permissive. But users will click anything and the dialog isn't
    // very scary, so whatever.
    return 'https://www.google.com/m8/feeds';
  }

  public function setUserData($data) {
    $xml = new SimpleXMLElement($data);
    $id = (string)$xml->id;
    $this->userData = array(
      'id'      => $id,
      'email'   => (string)$xml->author[0]->email,
      'real'    => (string)$xml->author[0]->name,

      // Guess account name from email address, this is just a hint anyway.
      'account' => head(explode('@', $id)),
    );
    return $this;
  }

  public function retrieveUserID() {
    return $this->userData['id'];
  }

  public function retrieveUserEmail() {
    return $this->userData['email'];
  }

  public function retrieveUserAccountName() {
    return $this->userData['account'];
  }

  public function retrieveUserProfileImage() {
    // No apparent API access to Plus yet.
    return null;
  }

  public function retrieveUserAccountURI() {
    // No apparent API access to Plus yet.
    return null;
  }

  public function retrieveUserRealName() {
    return $this->userData['real'];
  }

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
    return json_decode($response, true);
  }

}
