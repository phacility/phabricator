<?php

final class PassphraseCredentialRevealController
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
      ->needSecrets(true)
      ->executeOne();
    if (!$credential) {
      return new Aphront404Response();
    }

    $view_uri = '/K'.$credential->getID();

    if ($request->isFormPost()) {
      if ($credential->getSecret()) {
        $body = id(new PHUIFormLayoutView())
          ->appendChild(
            id(new AphrontFormTextAreaControl())
              ->setLabel(pht('Plaintext'))
              ->setValue($credential->getSecret()->openEnvelope()));
      } else {
        $body = pht('This credential has no associated secret.');
      }

      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Credential Secret'))
        ->appendChild($body)
        ->addCancelButton($view_uri, pht('Done'));

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
        'text on your screen. Before continuing, wrap your arms around your '.
        'monitor to create a human shield, keeping it safe from prying eyes. '.
        'Protect company secrets!');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Really show secret?'))
      ->appendChild($body)
      ->addSubmitButton(pht('Show Secret'))
      ->addCancelButton($view_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
