<?php

final class PhabricatorOAuthClientDeleteController
  extends PhabricatorOAuthClientController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $client = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($this->getClientPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$client) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $client->delete();
      $app_uri = $this->getApplicationURI();
      return id(new AphrontRedirectResponse())->setURI($app_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Delete OAuth Application?'))
      ->appendParagraph(
        pht(
          'Really delete the OAuth application %s?',
          phutil_tag('strong', array(), $client->getName())))
      ->addCancelButton($client->getViewURI())
      ->addSubmitButton(pht('Delete Application'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
