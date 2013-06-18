<?php

final class PhabricatorSettingsPanelExternalAccounts
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'external';
  }

  public function getPanelName() {
    return pht('External Accounts');
  }

  public function getPanelGroup() {
    return pht('Authentication');
  }

  public function isEnabled() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $providers = PhabricatorAuthProvider::getAllProviders();

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->needImages(true)
      ->execute();

    $linked_head = id(new PhabricatorHeaderView())
      ->setHeader(pht('Linked Accounts and Authentication'));

    $linked = id(new PhabricatorObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(pht('You have no linked accounts.'));

    $login_accounts = 0;
    foreach ($accounts as $account) {
      if ($account->isUsableForLogin()) {
        $login_accounts++;
      }
    }

    foreach ($accounts as $account) {
      $item = id(new PhabricatorObjectItemView());

      $provider = idx($providers, $account->getProviderKey());
      if ($provider) {
        $item->setHeader($provider->getProviderName());
        $can_unlink = $provider->shouldAllowAccountUnlink();
        if (!$can_unlink) {
          $item->addAttribute(pht('Permanently Linked'));
        }
      } else {
        $item->setHeader(
          pht('Unknown Account ("%s")', $account->getProviderKey()));
        $can_unlink = true;
      }

      $can_login = $account->isUsableForLogin();
      if (!$can_login) {
        $item->addAttribute(
          pht(
            'Disabled (an administrator has disabled login for this '.
            'account provider).'));
      }

      $can_unlink = $can_unlink && (!$can_login || ($login_accounts > 1));

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('delete')
          ->setWorkflow(true)
          ->setDisabled(!$can_unlink)
          ->setHref('/auth/unlink/'.$account->getProviderKey().'/'));

      $account_view = id(new PhabricatorAuthAccountView())
        ->setExternalAccount($account);

      if ($provider) {
        $account_view->setAuthProvider($provider);
      }

      $item->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'mmr mml mst mmb',
          ),
          $account_view));

      $linked->addItem($item);
    }

    $linkable_head = id(new PhabricatorHeaderView())
      ->setHeader(pht('Add External Account'));

    $linkable = id(new PhabricatorObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(
        pht('Your account is linked with all available providers.'));

    $accounts = mpull($accounts, null, 'getProviderKey');

    $providers = PhabricatorAuthProvider::getAllEnabledProviders();
    $providers = msort($providers, 'getProviderName');
    foreach ($providers as $key => $provider) {
      if (isset($accounts[$key])) {
        continue;
      }

      if (!$provider->shouldAllowAccountLink()) {
        continue;
      }

      $link_uri = '/auth/link/'.$provider->getProviderKey().'/';

      $item = id(new PhabricatorObjectItemView());
      $item->setHeader($provider->getProviderName());
      $item->setHref($link_uri);
      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('link')
          ->setHref($link_uri));

      $linkable->addItem($item);
    }

    return array(
      $linked_head,
      $linked,
      $linkable_head,
      $linkable,
    );
  }

}
