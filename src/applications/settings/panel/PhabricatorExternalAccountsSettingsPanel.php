<?php

final class PhabricatorExternalAccountsSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'external';
  }

  public function getPanelName() {
    return pht('External Accounts');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsAuthenticationPanelGroup::PANELGROUPKEY;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $providers = PhabricatorAuthProvider::getAllProviders();

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->needImages(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $linked_head = pht('Linked Accounts and Authentication');

    $linked = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(pht('You have no linked accounts.'));

    $login_accounts = 0;
    foreach ($accounts as $account) {
      if ($account->isUsableForLogin()) {
        $login_accounts++;
      }
    }

    foreach ($accounts as $account) {
      $item = new PHUIObjectItemView();

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

      $can_refresh = $provider && $provider->shouldAllowAccountRefresh();
      if ($can_refresh) {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-refresh')
            ->setHref('/auth/refresh/'.$account->getProviderKey().'/'));
      }

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-times')
          ->setWorkflow(true)
          ->setDisabled(!$can_unlink)
          ->setHref('/auth/unlink/'.$account->getProviderKey().'/'));

      if ($provider) {
        $provider->willRenderLinkedAccount($viewer, $item, $account);
      }

      $linked->addItem($item);
    }

    $linkable_head = pht('Add External Account');

    $linkable = id(new PHUIObjectItemListView())
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

      $item = id(new PHUIObjectItemView())
        ->setHeader($provider->getProviderName())
        ->setHref($link_uri)
        ->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-link')
            ->setHref($link_uri));

      $linkable->addItem($item);
    }

    $linked_box = $this->newBox($linked_head, $linked);
    $linkable_box = $this->newBox($linkable_head, $linkable);

    return array(
      $linked_box,
      $linkable_box,
    );
  }

}
