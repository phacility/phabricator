<?php

final class PhabricatorAuthDisableController
  extends PhabricatorAuthProviderConfigController {

  public function handleRequest(AphrontRequest $request) {
    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);
    $viewer = $request->getUser();
    $config_id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $config = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($config_id))
      ->executeOne();
    if (!$config) {
      return new Aphront404Response();
    }

    $is_enable = ($action === 'enable');

    if ($request->isDialogFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorAuthProviderConfigTransaction())
        ->setTransactionType(
          PhabricatorAuthProviderConfigTransaction::TYPE_ENABLE)
        ->setNewValue((int)$is_enable);

      $editor = id(new PhabricatorAuthProviderConfigEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($config, $xactions);

      return id(new AphrontRedirectResponse())->setURI(
        $this->getApplicationURI());
    }

    if ($is_enable) {
      $title = pht('Enable Provider?');
      if ($config->getShouldAllowRegistration()) {
        $body = pht(
          'Do you want to enable this provider? Users will be able to use '.
          'their existing external accounts to register new Phabricator '.
          'accounts and log in using linked accounts.');
      } else {
        $body = pht(
          'Do you want to enable this provider? Users will be able to log '.
          'in to Phabricator using linked accounts.');
      }
      $button = pht('Enable Provider');
    } else {
      // TODO: We could tailor this a bit more. In particular, we could
      // check if this is the last provider and either prevent if from
      // being disabled or force the user through like 35 prompts. We could
      // also check if it's the last provider linked to the acting user's
      // account and pop a warning like "YOU WILL NO LONGER BE ABLE TO LOGIN
      // YOU GOOF, YOU PROBABLY DO NOT MEAN TO DO THIS". None of this is
      // critical and we can wait to see how users manage to shoot themselves
      // in the feet. Shortly, `bin/auth` will be able to recover from these
      // types of mistakes.

      $title = pht('Disable Provider?');
      $body = pht(
        'Do you want to disable this provider? Users will not be able to '.
        'register or log in using linked accounts. If there are any users '.
        'without other linked authentication mechanisms, they will no longer '.
        'be able to log in. If you disable all providers, no one will be '.
        'able to log in.');
      $button = pht('Disable Provider');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($this->getApplicationURI())
      ->addSubmitButton($button);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
