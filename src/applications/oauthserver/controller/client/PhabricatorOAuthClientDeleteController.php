<?php

final class PhabricatorOAuthClientDeleteController
  extends PhabricatorOAuthClientController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $client = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$client) {
      return new Aphront404Response();
    }

    // TODO: This should be "disable", not "delete"!

    if ($request->isFormPost()) {
      $client->delete();
      $app_uri = $this->getApplicationURI();
      return id(new AphrontRedirectResponse())->setURI($app_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Delete OAuth Application?'))
      ->appendParagraph(
        pht(
          'Really delete the OAuth application %s?',
          phutil_tag('strong', array(), $client->getName())))
      ->addCancelButton($client->getViewURI())
      ->addSubmitButton(pht('Delete Application'));
  }

}
