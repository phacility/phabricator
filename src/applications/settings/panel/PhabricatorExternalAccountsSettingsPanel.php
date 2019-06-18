<?php

final class PhabricatorExternalAccountsSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'external';
  }

  public function getPanelName() {
    return pht('External Accounts');
  }

  public function getPanelMenuIcon() {
    return 'fa-users';
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

    foreach ($accounts as $account) {
      $item = new PHUIObjectItemView();

      $config = $account->getProviderConfig();
      $provider = $config->getProvider();

      $item->setHeader($provider->getProviderName());
      $can_unlink = $provider->shouldAllowAccountUnlink();
      if (!$can_unlink) {
        $item->addAttribute(pht('Permanently Linked'));
      }

      $can_login = $account->isUsableForLogin();
      if (!$can_login) {
        $item->addAttribute(
          pht(
            'Disabled (an administrator has disabled login for this '.
            'account provider).'));
      }

      $can_refresh = $provider->shouldAllowAccountRefresh();
      if ($can_refresh) {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-refresh')
            ->setHref('/auth/refresh/'.$config->getID().'/'));
      }

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-times')
          ->setWorkflow(true)
          ->setDisabled(!$can_unlink)
          ->setHref('/auth/unlink/'.$account->getID().'/'));

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

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->withIsEnabled(true)
      ->execute();
    $configs = msortv($configs, 'getSortVector');

    $account_map = mgroup($accounts, 'getProviderConfigPHID');


    foreach ($configs as $config) {
      $provider = $config->getProvider();

      if (!$provider->shouldAllowAccountLink()) {
        continue;
      }

      // Don't show the user providers they already have linked.
      if (isset($account_map[$config->getPHID()])) {
        continue;
      }

      $link_uri = '/auth/link/'.$config->getID().'/';

      $link_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-link')
        ->setHref($link_uri)
        ->setColor(PHUIButtonView::GREY)
        ->setText(pht('Link External Account'));

      $item = id(new PHUIObjectItemView())
        ->setHeader($config->getDisplayName())
        ->setHref($link_uri)
        ->setImageIcon($config->newIconView())
        ->setSideColumn($link_button);

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
