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
final class PhabricatorOAuthServerTestController
extends PhabricatorOAuthServerController {

  public function shouldRequireLogin() {
    return true;
  }

  public function processRequest() {
    $request      = $this->getRequest();
    $current_user = $request->getUser();
    $server       = new PhabricatorOAuthServer();
    $panels       = array();
    $results      = array();


    if ($request->isFormPost()) {
      $action = $request->getStr('action');
      switch ($action) {
        case 'testclientauthorization':
          $user_phid   = $current_user->getPHID();
          $client_phid = $request->getStr('client_phid');
          $client      = id(new PhabricatorOAuthServerClient)
            ->loadOneWhere('phid = %s', $client_phid);
          if (!$client) {
            throw new Exception('Failed to load client!');
          }
          if ($client->getCreatorPHID() != $user_phid ||
              $current_user->getPHID()  != $user_phid) {
              throw new Exception(
                'Only allowed to make test data for yourself '.
                'for clients you own!'
              );
          }
          // blankclientauthorizations don't get scope
          $scope = array();
          $server->setUser($current_user);
          $server->setClient($client);
          $authorization = $server->authorizeClient($scope);
          return id(new AphrontRedirectResponse())
            ->setURI('/oauthserver/clientauthorization/?edited='.
                     $authorization->getPHID());
          break;
        default:
          break;
      }
    }
  }
}
