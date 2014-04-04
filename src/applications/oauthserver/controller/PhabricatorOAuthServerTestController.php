<?php

final class PhabricatorOAuthServerTestController
  extends PhabricatorOAuthServerController {

  private $id;

  public function shouldRequireLogin() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $panels       = array();
    $results      = array();

    $client = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
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
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Already Authorized'))
        ->appendParagraph(
          pht(
            'You have already authorized this application to access your '.
            'account.'))
        ->addCancelButton($view_uri, pht('Close'));

      return id(new AphrontDialogResponse())->setDialog($dialog);
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

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Authorize Application?'))
      ->appendParagraph(
        pht(
          'This will create an authorization, permitting %s to access '.
          'your account.',
          phutil_tag('strong', array(), $client->getName())))
      ->addCancelButton($view_uri)
      ->addSubmitButton(pht('Authorize Application'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
