<?php

final class PhabricatorAuthUnlinkController
  extends PhabricatorAuthController {

  private $providerKey;

  public function willProcessRequest(array $data) {
    $this->providerKey = $data['pkey'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

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

    // Check that this account isn't the last account which can be used to
    // login. We prevent you from removing the last account.
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
        return $this->renderLastUsableAccountErrorDialog();
      }
    }

    if ($request->isDialogFormPost()) {
      $account->delete();

      id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
        $viewer,
        $request->getCookie(PhabricatorCookies::COOKIE_SESSION));

      return id(new AphrontRedirectResponse())->setURI($this->getDoneURI());
    }

    return $this->renderConfirmDialog($account);
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

  private function renderLastUsableAccountErrorDialog() {
    $dialog = id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setTitle(pht('Last Valid Account'))
      ->appendChild(
        pht(
          'You can not unlink this account because you have no other '.
          'valid login accounts. If you removed it, you would be unable '.
          'to login. Add another authentication method before removing '.
          'this one.'))
      ->addCancelButton($this->getDoneURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function renderConfirmDialog() {
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

    $dialog = id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setTitle($title)
      ->appendParagraph($body)
      ->appendParagraph(
        pht(
          'Note: Unlinking an authentication provider will terminate any '.
          'other active login sessions.'))
      ->addSubmitButton(pht('Unlink Account'))
      ->addCancelButton($this->getDoneURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
