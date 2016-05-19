<?php

final class PhabricatorAuthSSHKeyDeactivateController
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

    $cancel_uri = $key->getURI();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $cancel_uri);

    if ($request->isFormPost()) {

      // TODO: Convert to transactions.
      $key->setIsActive(null);
      $key->save();

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    $name = phutil_tag('strong', array(), $key->getName());

    return $this->newDialog()
      ->setTitle(pht('Deactivate SSH Public Key'))
      ->appendParagraph(
        pht(
          'The key "%s" will be permanently deactivated, and you will no '.
          'longer be able to use the corresponding private key to '.
          'authenticate.',
          $name))
      ->addSubmitButton(pht('Deactivate Public Key'))
      ->addCancelButton($cancel_uri);
  }

}
