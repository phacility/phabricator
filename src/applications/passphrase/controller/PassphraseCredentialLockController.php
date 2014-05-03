<?php

final class PassphraseCredentialLockController
  extends PassphraseController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
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
          pht(
            'This credential has been locked and the secret is '.
            'hidden forever. Anything relying on this credential will '.
            'still function. This operation can not be undone.'))
        ->addCancelButton($view_uri, pht('Close'));
    }

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType(PassphraseCredentialTransaction::TYPE_LOCK)
        ->setNewValue(1);

      $editor = id(new PassphraseCredentialTransactionEditor())
        ->setActor($viewer)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($credential, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Really lock credential?'))
      ->appendChild(
        pht(
          'This credential will be locked and the secret will be '.
          'hidden forever. Anything relying on this credential will '.
          'still function. This operation can not be undone.'))
      ->addSubmitButton(pht('Lock Credential'))
      ->addCancelButton($view_uri);
  }

}
