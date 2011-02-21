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

abstract class PhabricatorOAuthProvider {

  const PROVIDER_FACEBOOK = 'facebook';
  const PROVIDER_GITHUB   = 'github';

  abstract public function getProviderKey();
  abstract public function getProviderName();
  abstract public function isProviderEnabled();
  abstract public function getRedirectURI();
  abstract public function getClientID();
  abstract public function getClientSecret();
  abstract public function getAuthURI();
  abstract public function getTokenURI();
  abstract public function getUserInfoURI();

  public function __construct() {

  }

  public static function newProvider($which) {
    switch ($which) {
      case self::PROVIDER_FACEBOOK:
        $class = 'PhabricatorOAuthProviderFacebook';
        break;
      case self::PROVIDER_GITHUB:
        $class = 'PhabricatorOAuthProviderGithub';
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
    );
    $providers = array();
    foreach ($all as $provider) {
      $providers[] = self::newProvider($provider);
    }
    return $providers;
  }

}
