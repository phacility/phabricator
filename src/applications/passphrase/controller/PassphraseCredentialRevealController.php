<?php

final class PassphraseCredentialRevealController
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
      ->needSecrets(true)
      ->executeOne();
    if (!$credential) {
      return new Aphront404Response();
    }

    $view_uri = '/K'.$credential->getID();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $view_uri);
    $is_locked = $credential->getIsLocked();

    if ($is_locked) {
      return $this->newDialog()
        ->setUser($viewer)
        ->setTitle(pht('Credential is locked'))
        ->appendChild(
          pht(
            'This credential can not be shown, because it is locked.'))
        ->addCancelButton($view_uri);
    }

    if ($request->isFormPost()) {
      $secret = $credential->getSecret();
      if (!$secret) {
        $body = pht('This credential has no associated secret.');
      } else if (!strlen($secret->openEnvelope())) {
        $body = pht('This credential has an empty secret.');
      } else {
        $body = id(new PHUIFormLayoutView())
          ->appendChild(
            id(new AphrontFormTextAreaControl())
              ->setLabel(pht('Plaintext'))
              ->setReadOnly(true)
              ->setCustomClass('PhabricatorMonospaced')
              ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
              ->setValue($secret->openEnvelope()));
      }

      // NOTE: Disable workflow on the cancel button to reload the page so
      // the viewer can see that their view was logged.

      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle(pht('Credential Secret (%s)', $credential->getMonogram()))
        ->appendChild($body)
        ->setDisableWorkflowOnCancel(true)
        ->addCancelButton($view_uri, pht('Done'));

      $type_secret = PassphraseCredentialTransaction::TYPE_LOOKEDATSECRET;
      $xactions = array(
        id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_secret)
          ->setNewValue(true),
      );

      $editor = id(new PassphraseCredentialTransactionEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($credential, $xactions);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    if ($is_serious) {
      $body = pht(
        'The secret associated with this credential will be shown in plain '.
        'text on your screen.');
    } else {
      $body = pht(
        'The secret associated with this credential will be shown in plain '.
        'text on your screen. Before continuing, wrap your arms around '.
        'your monitor to create a human shield, keeping it safe from '.
        'prying eyes. Protect company secrets!');
    }
    return $this->newDialog()
      ->setUser($viewer)
      ->setTitle(pht('Really show secret?'))
      ->appendChild($body)
      ->addSubmitButton(pht('Show Secret'))
      ->addCancelButton($view_uri);
  }

}
