<?php

final class PassphraseCredentialPublicController
  extends PassphraseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->executeOne();
    if (!$credential) {
      return new Aphront404Response();
    }

    $type = PassphraseCredentialType::getTypeByConstant(
      $credential->getCredentialType());
    if (!$type) {
      throw new Exception(pht('Credential has invalid type "%s"!', $type));
    }

    if (!$type->hasPublicKey()) {
      throw new Exception(pht('Credential has no public key!'));
    }

    $view_uri = '/'.$credential->getMonogram();

    $public_key = $type->getPublicKey($viewer, $credential);

    $body = id(new PHUIFormLayoutView())
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Public Key'))
          ->setReadOnly(true)
          ->setValue($public_key));

    return $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Public Key (%s)', $credential->getMonogram()))
      ->appendChild($body)
      ->addCancelButton($view_uri, pht('Done'));

  }

}
