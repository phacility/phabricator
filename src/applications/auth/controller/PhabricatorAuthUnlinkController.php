<?php

final class PhabricatorAuthUnlinkController
  extends PhabricatorAuthController {

  private $providerKey;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $this->providerKey = $request->getURIData('pkey');

    list($type, $domain) = explode(':', $this->providerKey, 2);

    // Check that this account link actually exists. We don't require the
    // provider to exist because we want users to be able to delete links to
    // dead accounts if they want.
    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountType = %s AND accountDomain = %s AND userPHID = %s',
      $type,
      $domain,
      $viewer->getPHID());
    if (!$account) {
      return $this->renderNoAccountErrorDialog();
    }

    // Check that the provider (if it exists) allows accounts to be unlinked.
    $provider_key = $this->providerKey;
    $provider = PhabricatorAuthProvider::getEnabledProviderByKey($provider_key);
    if ($provider) {
      if (!$provider->shouldAllowAccountUnlink()) {
        return $this->renderNotUnlinkableErrorDialog($provider);
      }
    }

    $confirmations = $request->getStrList('confirmations');
    $confirmations = array_fuse($confirmations);

    if (!$request->isFormPost() || !isset($confirmations['unlink'])) {
      return $this->renderConfirmDialog($confirmations);
    }

    // Check that this account isn't the only account which can be used to
    // login. We warn you when you remove your only login account.
    if ($account->isUsableForLogin()) {
      $other_accounts = id(new PhabricatorExternalAccount())->loadAllWhere(
        'userPHID = %s',
        $viewer->getPHID());

      $valid_accounts = 0;
      foreach ($other_accounts as $other_account) {
        if ($other_account->isUsableForLogin()) {
          $valid_accounts++;
        }
      }

      if ($valid_accounts < 2) {
        if (!isset($confirmations['only'])) {
          return $this->renderOnlyUsableAccountConfirmDialog($confirmations);
        }
      }
    }

    $account->delete();

    id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
      $viewer,
      new PhutilOpaqueEnvelope(
        $request->getCookie(PhabricatorCookies::COOKIE_SESSION)));

    return id(new AphrontRedirectResponse())->setURI($this->getDoneURI());
  }

  private function getDoneURI() {
    return '/settings/panel/external/';
  }

  private function renderNoAccountErrorDialog() {
    $dialog = id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setTitle(pht('No Such Account'))
      ->appendChild(
        pht(
          'You can not unlink this account because it is not linked.'))
      ->addCancelButton($this->getDoneURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function renderNotUnlinkableErrorDialog(
    PhabricatorAuthProvider $provider) {

    $dialog = id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setTitle(pht('Permanent Account Link'))
      ->appendChild(
        pht(
          'You can not unlink this account because the administrator has '.
          'configured Phabricator to make links to %s accounts permanent.',
          $provider->getProviderName()))
      ->addCancelButton($this->getDoneURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function renderOnlyUsableAccountConfirmDialog(array $confirmations) {
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
      ->addCancelButton($this->getDoneURI())
      ->addSubmitButton(pht('Unlink External Account'));
  }

  private function renderConfirmDialog(array $confirmations) {
    $confirmations[] = 'unlink';

    $provider_key = $this->providerKey;
    $provider = PhabricatorAuthProvider::getEnabledProviderByKey($provider_key);

    if ($provider) {
      $title = pht('Unlink "%s" Account?', $provider->getProviderName());
      $body = pht(
        'You will no longer be able to use your %s account to '.
        'log in to Phabricator.',
        $provider->getProviderName());
    } else {
      $title = pht('Unlink Account?');
      $body = pht(
        'You will no longer be able to use this account to log in '.
        'to Phabricator.');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->addHiddenInput('confirmations', implode(',', $confirmations))
      ->appendParagraph($body)
      ->appendParagraph(
        pht(
          'Note: Unlinking an authentication provider will terminate any '.
          'other active login sessions.'))
      ->addSubmitButton(pht('Unlink Account'))
      ->addCancelButton($this->getDoneURI());
  }

}
