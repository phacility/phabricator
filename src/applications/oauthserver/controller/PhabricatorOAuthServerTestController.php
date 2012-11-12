<?php

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
