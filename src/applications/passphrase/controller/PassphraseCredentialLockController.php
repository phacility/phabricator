<?php

final class PassphraseCredentialLockController
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

    if ($credential->getIsLocked()) {
      return $this->newDialog()
        ->setTitle(pht('Credential Already Locked'))
        ->appendChild(
          pht('This credential is already locked.'))
        ->addCancelButton($view_uri, pht('Close'));
    }

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType(
          PassphraseCredentialConduitTransaction::TRANSACTIONTYPE)
        ->setNewValue(0);

      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType(
          PassphraseCredentialLockTransaction::TRANSACTIONTYPE)
        ->setNewValue(1);

      $editor = id(new PassphraseCredentialTransactionEditor())
        ->setActor($viewer)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($credential, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Lock Credential'))
      ->appendChild(
        pht(
          'This credential will be locked and the secret will be hidden '.
          'forever. If Conduit access is enabled, it will be revoked. '.
          'Anything relying on this credential will still function. This '.
          'operation can not be undone.'))
      ->addSubmitButton(pht('Lock Credential'))
      ->addCancelButton($view_uri);
  }

}
