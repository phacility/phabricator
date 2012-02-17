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

abstract class PhabricatorOAuthProvider {

  const PROVIDER_FACEBOOK    = 'facebook';
  const PROVIDER_GITHUB      = 'github';
  const PROVIDER_GOOGLE      = 'google';
  const PROVIDER_PHABRICATOR = 'phabricator';

  private $accessToken;

  abstract public function getProviderKey();
  abstract public function getProviderName();
  abstract public function isProviderEnabled();
  abstract public function isProviderLinkPermanent();
  abstract public function isProviderRegistrationEnabled();
  abstract public function getClientID();
  abstract public function renderGetClientIDHelp();
  abstract public function getClientSecret();
  abstract public function renderGetClientSecretHelp();
  abstract public function getAuthURI();
  abstract public function getTestURIs();

  /**
   * If the provider needs extra stuff in the auth request, return it here.
   * For example, Google needs a response_type parameter.
   */
  public function getExtraAuthParameters() {
    return array();
  }

  abstract public function getTokenURI();

  /**
   * If the provider needs extra stuff in the token request, return it here.
   * For example, Google needs a grant_type parameter.
   */
  public function getExtraTokenParameters() {
    return array();
  }

  abstract public function getUserInfoURI();
  abstract public function getMinimumScope();

  abstract public function setUserData($data);
  abstract public function retrieveUserID();
  abstract public function retrieveUserEmail();
  abstract public function retrieveUserAccountName();
  abstract public function retrieveUserProfileImage();
  abstract public function retrieveUserAccountURI();
  abstract public function retrieveUserRealName();

  /**
   * Override this if the provider returns the token response as, e.g., JSON
   * or XML.
   */
  public function decodeTokenResponse($response) {
    $data = null;
    parse_str($response, $data);
    return $data;
  }

  public function __construct() {

  }

  /**
   * This is where the OAuth provider will redirect the user after the user
   * grants Phabricator access.
   */
  final public function getRedirectURI() {
    $key = $this->getProviderKey();
    return PhabricatorEnv::getURI('/oauth/'.$key.'/login/');
  }

  final public function setAccessToken($access_token) {
    $this->accessToken = $access_token;
    return $this;
  }

  final public function getAccessToken() {
    return $this->accessToken;
  }

  public static function newProvider($which) {
    switch ($which) {
      case self::PROVIDER_FACEBOOK:
        $class = 'PhabricatorOAuthProviderFacebook';
        break;
      case self::PROVIDER_GITHUB:
        $class = 'PhabricatorOAuthProviderGitHub';
        break;
      case self::PROVIDER_GOOGLE:
        $class = 'PhabricatorOAuthProviderGoogle';
        break;
      case self::PROVIDER_PHABRICATOR:
        $class = 'PhabricatorOAuthProviderPhabricator';
        break;
      default:
        throw new Exception('Unknown OAuth provider.');
    }
    PhutilSymbolLoader::loadClass($class);
    return newv($class, array());
  }

  public static function getAllProviders() {
    $all = array(
      self::PROVIDER_FACEBOOK,
      self::PROVIDER_GITHUB,
      self::PROVIDER_GOOGLE,
      self::PROVIDER_PHABRICATOR,
    );
    $providers = array();
    foreach ($all as $provider) {
      $providers[$provider] = self::newProvider($provider);
    }
    return $providers;
  }

}
