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
 * Implements core OAuth 2.0 Server logic.
 *
 * This class should be used behind business logic that parses input to
 * determine pertinent @{class:PhabricatorUser} $user,
 * @{class:PhabricatorOAuthServerClient} $client(s),
 * @{class:PhabricatorOAuthServerAuthorizationCode} $code(s), and.
 * @{class:PhabricatorOAuthServerAccessToken} $token(s).
 *
 * For an OAuth 2.0 server, there are two main steps:
 *
 * 1) Authorization - the user authorizes a given client to access the data
 * the OAuth 2.0 server protects.  Once this is achieved / if it has
 * been achived already, the OAuth server sends the client an authorization
 * code.
 * 2) Access Token - the client should send the authorization code received in
 * step 1 along with its id and secret to the OAuth server to receive an
 * access token.  This access token can later be used to access Phabricator
 * data on behalf of the user.
 *
 * @task auth Authorizing @{class:PhabricatorOAuthServerClient}s and
 *            generating @{class:PhabricatorOAuthServerAuthorizationCode}s
 * @task token Validating @{class:PhabricatorOAuthServerAuthorizationCode}s
 *             and generating @{class:PhabricatorOAuthServerAccessToken}s
 * @task internal Internals
 *
 * @group oauthserver
 */
final class PhabricatorOAuthServer {

  const AUTHORIZATION_CODE_TIMEOUT = 300;

  private $user;

  /**
   * @group internal
   */
  private function getUser() {
    return $this->user;
  }

  public function __construct(PhabricatorUser $user) {
    if (!$user) {
      throw new Exception('Must specify a Phabricator $user to constructor!');
    }
    $this->user = $user;
  }

  /**
   * @task auth
   */
  public function userHasAuthorizedClient(
    PhabricatorOAuthServerClient $client) {

    $authorization = id(new PhabricatorOAuthClientAuthorization())->
      loadOneWhere('userPHID = %s AND clientPHID = %s',
                   $this->getUser()->getPHID(),
                   $client->getPHID());

    if (empty($authorization)) {
      return false;
    }

    return true;
  }

  /**
   * @task auth
   */
  public function authorizeClient(PhabricatorOAuthServerClient $client) {
    $authorization = new PhabricatorOAuthClientAuthorization();
    $authorization->setUserPHID($this->getUser()->getPHID());
    $authorization->setClientPHID($client->getPHID());
    $authorization->save();
  }

  /**
   * @task auth
   */
  public function generateAuthorizationCode(
    PhabricatorOAuthServerClient $client) {

    $code = Filesystem::readRandomCharacters(32);

    $authorization_code = new PhabricatorOAuthServerAuthorizationCode();
    $authorization_code->setCode($code);
    $authorization_code->setClientPHID($client->getPHID());
    $authorization_code->setClientSecret($client->getSecret());
    $authorization_code->setUserPHID($this->getUser()->getPHID());
    $authorization_code->save();

    return $authorization_code;
  }

  /**
   * @task token
   */
  public function generateAccessToken(PhabricatorOAuthServerClient $client) {

    $token = Filesystem::readRandomCharacters(32);

    $access_token = new PhabricatorOAuthServerAccessToken();
    $access_token->setToken($token);
    $access_token->setUserPHID($this->getUser()->getPHID());
    $access_token->setClientPHID($client->getPHID());
    $access_token->setDateExpires(0);
    $access_token->save();

    return $access_token;
  }

  /**
   * @task token
   */
  public function validateAuthorizationCode(
    PhabricatorOAuthServerAuthorizationCode $test_code,
    PhabricatorOAuthServerAuthorizationCode $valid_code) {

    // check that all the meta data matches
    if ($test_code->getClientPHID() != $valid_code->getClientPHID()) {
      return false;
    }
    if ($test_code->getClientSecret() != $valid_code->getClientSecret()) {
      return false;
    }

    // check that the authorization code hasn't timed out
    $created_time = $test_code->getDateCreated();
    $must_be_used_by = $created_time + self::AUTHORIZATION_CODE_TIMEOUT;
    return (time() < $must_be_used_by);
  }

}
