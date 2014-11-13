<?php

final class PhabricatorAuthSSHKeyDeleteController
  extends PhabricatorAuthSSHKeyController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $key = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$key) {
      return new Aphront404Response();
    }

    $cancel_uri = $key->getObject()->getSSHPublicKeyManagementURI($viewer);

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $cancel_uri);

    if ($request->isFormPost()) {
      // TODO: It would be nice to write an edge transaction here or something.
      $key->delete();
      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    $name = phutil_tag('strong', array(), $key->getName());

    return $this->newDialog()
      ->setTitle(pht('Really delete SSH Public Key?'))
      ->appendParagraph(
        pht(
          'The key "%s" will be permanently deleted, and you will not longer '.
          'be able to use the corresponding private key to authenticate.',
          $name))
      ->addSubmitButton(pht('Delete Public Key'))
      ->addCancelButton($cancel_uri);
  }

}
