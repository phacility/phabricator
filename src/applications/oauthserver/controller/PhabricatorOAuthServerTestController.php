<?php

final class PhabricatorOAuthServerTestController
  extends PhabricatorOAuthServerController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $client = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$client) {
      return new Aphront404Response();
    }

    $view_uri = $client->getViewURI();

    // Look for an existing authorization.
    $authorization = id(new PhabricatorOAuthClientAuthorizationQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withClientPHIDs(array($client->getPHID()))
      ->executeOne();
    if ($authorization) {
      return $this->newDialog()
        ->setTitle(pht('Already Authorized'))
        ->appendParagraph(
          pht(
            'You have already authorized this application to access your '.
            'account.'))
        ->addCancelButton($view_uri, pht('Close'));
    }

    if ($request->isFormPost()) {
      $server = id(new PhabricatorOAuthServer())
        ->setUser($viewer)
        ->setClient($client);

      $scope = array();
      $authorization = $server->authorizeClient($scope);

      $id = $authorization->getID();
      $panel_uri = '/settings/panel/oauthorizations/?id='.$id;

      return id(new AphrontRedirectResponse())->setURI($panel_uri);
    }

    // TODO: It would be nice to put scope options in this dialog, maybe?

    return $this->newDialog()
      ->setTitle(pht('Authorize Application?'))
      ->appendParagraph(
        pht(
          'This will create an authorization, permitting %s to access '.
          'your account.',
          phutil_tag('strong', array(), $client->getName())))
      ->addCancelButton($view_uri)
      ->addSubmitButton(pht('Authorize Application'));
  }
}
