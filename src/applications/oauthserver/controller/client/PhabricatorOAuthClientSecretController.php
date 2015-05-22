<?php

final class PhabricatorOAuthClientSecretController
  extends PhabricatorOAuthClientController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $client = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($this->getClientPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$client) {
      return new Aphront404Response();
    }

    $view_uri = $client->getViewURI();
    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $view_uri);

    if ($request->isFormPost()) {
      $secret = $client->getSecret();
      $body = id(new PHUIFormLayoutView())
        ->appendChild(
          id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Plaintext'))
          ->setReadOnly(true)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($secret));

      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle(pht('Application Secret'))
        ->appendChild($body)
        ->addCancelButton($view_uri, pht('Done'));

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }


    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    if ($is_serious) {
      $body = pht(
        'The secret associated with this OAuth application will be shown in '.
        'plain text on your screen.');
    } else {
      $body = pht(
        'The secret associated with this OAuth application will be shown in '.
        'plain text on your screen. Before continuing, wrap your arms around '.
        'your monitor to create a human shield, keeping it safe from prying '.
        'eyes. Protect company secrets!');
    }
    return $this->newDialog()
      ->setUser($viewer)
      ->setTitle(pht('Really show application secret?'))
      ->appendChild($body)
      ->addSubmitButton(pht('Show Application Secret'))
      ->addCancelButton($view_uri);
  }

}
