<?php

final class PassphraseCredentialConduitController
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

    $view_uri = '/K'.$credential->getID();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $view_uri);

    $type = PassphraseCredentialType::getTypeByConstant(
      $credential->getCredentialType());
    if (!$type) {
      throw new Exception(pht('Credential has invalid type "%s"!', $type));
    }

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType(PassphraseCredentialTransaction::TYPE_CONDUIT)
        ->setNewValue(!$credential->getAllowConduit());

      $editor = id(new PassphraseCredentialTransactionEditor())
        ->setActor($viewer)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($credential, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($credential->getAllowConduit()) {
      return $this->newDialog()
        ->setTitle(pht('Prevent Conduit access?'))
        ->appendChild(
          pht(
            'This credential and its secret will no longer be able '.
            'to be retrieved using the `%s` method in Conduit.',
            'passphrase.query'))
        ->addSubmitButton(pht('Prevent Conduit Access'))
        ->addCancelButton($view_uri);
    } else {
      return $this->newDialog()
        ->setTitle(pht('Allow Conduit access?'))
        ->appendChild(
          pht(
            'This credential will be able to be retrieved via the Conduit '.
            'API by users who have access to this credential. You should '.
            'only enable this for credentials which need to be accessed '.
            'programmatically (such as from build agents).'))
        ->addSubmitButton(pht('Allow Conduit Access'))
        ->addCancelButton($view_uri);
    }
  }

}
