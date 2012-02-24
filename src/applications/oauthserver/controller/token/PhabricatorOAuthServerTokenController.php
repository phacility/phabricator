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
 * @group oauthserver
 */
final class PhabricatorOAuthServerTokenController
extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request       = $this->getRequest();
    $code          = $request->getStr('code');
    $client_phid   = $request->getStr('client_id');
    $client_secret = $request->getStr('client_secret');
    $response      = new PhabricatorOAuthResponse();
    $server        = new PhabricatorOAuthServer();
    if (!$code) {
      return $response->setMalformed(
        'Required parameter code missing.'
      );
    }
    if (!$client_phid) {
      return $response->setMalformed(
        'Required parameter client_id missing.'
      );
    }
    if (!$client_secret) {
      return $response->setMalformed(
        'Required parameter client_secret missing.'
      );
    }

    $client = id(new PhabricatorOAuthServerClient())
      ->loadOneWhere('phid = %s', $client_phid);
    if (!$client) {
      return $response->setNotFound(
        'Client with client_id '.$client_phid.' not found.'
      );
    }
    $server->setClient($client);

    $auth_code = id(new PhabricatorOAuthServerAuthorizationCode())
      ->loadOneWhere('code = %s', $code);
    if (!$auth_code) {
      return $response->setNotFound(
        'Authorization code '.$code.' not found.'
      );
    }

    $user_phid = $auth_code->getUserPHID();
    $user = id(new PhabricatorUser())
      ->loadOneWhere('phid = %s', $user_phid);
    if (!$user) {
      return $response->setNotFound(
        'User with phid '.$user_phid.' not found.'
      );
    }
    $server->setUser($user);

    $test_code = new PhabricatorOAuthServerAuthorizationCode();
    $test_code->setClientSecret($client_secret);
    $test_code->setClientPHID($client_phid);
    $is_good_code = $server->validateAuthorizationCode($auth_code,
                                                       $test_code);
    if (!$is_good_code) {
      return $response->setMalformed(
        'Invalid authorization code '.$code.'.'
      );
    }

    $scope = AphrontWriteGuard::beginScopedUnguardedWrites();
    $access_token = $server->generateAccessToken();
    if ($access_token) {
      $auth_code->delete();
      $result = array(
         'access_token' => $access_token->getToken(),
         'token_type'   => 'Bearer',
         'expires_in'   => PhabricatorOAuthServer::ACCESS_TOKEN_TIMEOUT,
       );
      return $response->setContent($result);
    }

    return $response->setMalformed('Request is malformed in some way.');
  }
}
