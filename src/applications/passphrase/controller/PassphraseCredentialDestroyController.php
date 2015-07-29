<?php

final class PassphraseCredentialDestroyController
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
          PhabricatorPolicyCapability::CAN_EDIT,
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

    $view_uri = '/K'.$credential->getID();

    if ($request->isFormPost()) {

      $xactions = array();
      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType(PassphraseCredentialTransaction::TYPE_DESTROY)
        ->setNewValue(1);

      $editor = id(new PassphraseCredentialTransactionEditor())
        ->setActor($viewer)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($credential, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    return $this->newDialog()
      ->setUser($viewer)
      ->setTitle(pht('Really destroy credential?'))
      ->appendChild(
        pht(
          'This credential will be deactivated and the secret will be '.
          'unrecoverably destroyed. Anything relying on this credential '.
          'will cease to function. This operation can not be undone.'))
      ->addSubmitButton(pht('Destroy Credential'))
      ->addCancelButton($view_uri);
  }

}
