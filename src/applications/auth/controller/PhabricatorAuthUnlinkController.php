<?php

final class PhabricatorAuthUnlinkController
  extends PhabricatorAuthController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $account = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $done_uri = '/settings/panel/external/';

    $config = $account->getProviderConfig();
    $provider = $config->getProvider();
    if (!$provider->shouldAllowAccountUnlink()) {
      return $this->renderNotUnlinkableErrorDialog($provider, $done_uri);
    }

    $confirmations = $request->getStrList('confirmations');
    $confirmations = array_fuse($confirmations);

    if (!$request->isFormOrHisecPost() || !isset($confirmations['unlink'])) {
      return $this->renderConfirmDialog($confirmations, $config, $done_uri);
    }

    // Check that this account isn't the only account which can be used to
    // login. We warn you when you remove your only login account.
    if ($account->isUsableForLogin()) {
      $other_accounts = id(new PhabricatorExternalAccountQuery())
        ->setViewer($viewer)
        ->withUserPHIDs(array($viewer->getPHID()))
        ->execute();

      $valid_accounts = 0;
      foreach ($other_accounts as $other_account) {
        if ($other_account->isUsableForLogin()) {
          $valid_accounts++;
        }
      }

      if ($valid_accounts < 2) {
        if (!isset($confirmations['only'])) {
          return $this->renderOnlyUsableAccountConfirmDialog(
            $confirmations,
            $done_uri);
        }
      }
    }

    $workflow_key = sprintf(
      'account.unlink(%s)',
      $account->getPHID());

    $hisec_token = id(new PhabricatorAuthSessionEngine())
      ->setWorkflowKey($workflow_key)
      ->requireHighSecurityToken($viewer, $request, $done_uri);

    $account->unlinkAccount();

    id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
      $viewer,
      new PhutilOpaqueEnvelope(
        $request->getCookie(PhabricatorCookies::COOKIE_SESSION)));

    return id(new AphrontRedirectResponse())->setURI($done_uri);
  }

  private function renderNotUnlinkableErrorDialog(
    PhabricatorAuthProvider $provider,
    $done_uri) {

    return $this->newDialog()
      ->setTitle(pht('Permanent Account Link'))
      ->appendChild(
        pht(
          'You can not unlink this account because the administrator has '.
          'configured this server to make links to "%s" accounts permanent.',
          $provider->getProviderName()))
      ->addCancelButton($done_uri);
  }

  private function renderOnlyUsableAccountConfirmDialog(
    array $confirmations,
    $done_uri) {

    $confirmations[] = 'only';

    return $this->newDialog()
      ->setTitle(pht('Unlink Your Only Login Account?'))
      ->addHiddenInput('confirmations', implode(',', $confirmations))
      ->appendParagraph(
        pht(
          'This is the only external login account linked to your Phabicator '.
          'account. If you remove it, you may no longer be able to log in.'))
      ->appendParagraph(
        pht(
          'If you lose access to your account, you can recover access by '.
          'sending yourself an email login link from the login screen.'))
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Unlink External Account'));
  }

  private function renderConfirmDialog(
    array $confirmations,
    PhabricatorAuthProviderConfig $config,
    $done_uri) {

    $confirmations[] = 'unlink';
    $provider = $config->getProvider();

    $title = pht('Unlink "%s" Account?', $provider->getProviderName());
    $body = pht(
      'You will no longer be able to use your %s account to '.
      'log in.',
      $provider->getProviderName());

    return $this->newDialog()
      ->setTitle($title)
      ->addHiddenInput('confirmations', implode(',', $confirmations))
      ->appendParagraph($body)
      ->appendParagraph(
        pht(
          'Note: Unlinking an authentication provider will terminate any '.
          'other active login sessions.'))
      ->addSubmitButton(pht('Unlink Account'))
      ->addCancelButton($done_uri);
  }

}
