<?php

final class PhabricatorOAuthClientTestController
  extends PhabricatorOAuthClientController {

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

    $done_uri = $client->getViewURI();

    if ($request->isFormPost()) {
      $server = id(new PhabricatorOAuthServer())
        ->setUser($viewer)
        ->setClient($client);

      // Create an authorization if we don't already have one.
      $authorization = id(new PhabricatorOAuthClientAuthorizationQuery())
        ->setViewer($viewer)
        ->withUserPHIDs(array($viewer->getPHID()))
        ->withClientPHIDs(array($client->getPHID()))
        ->executeOne();
      if (!$authorization) {
        $scope = array();
        $authorization = $server->authorizeClient($scope);
      }

      $access_token = $server->generateAccessToken();

      $form = id(new AphrontFormView())
        ->setViewer($viewer)
        ->appendInstructions(
          pht(
            'Keep this token private, it allows any bearer to access '.
            'your account on behalf of this application.'))
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('Token'))
            ->setValue($access_token->getToken()));

      return $this->newDialog()
        ->setTitle(pht('OAuth Access Token'))
        ->appendForm($form)
        ->addCancelButton($done_uri, pht('Close'));
    }

    // TODO: It would be nice to put scope options in this dialog, maybe?

    return $this->newDialog()
      ->setTitle(pht('Authorize Application?'))
      ->appendParagraph(
        pht(
          'This will create an authorization and OAuth token, permitting %s '.
          'to access your account.',
          phutil_tag('strong', array(), $client->getName())))
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Authorize Application'));
  }
}
