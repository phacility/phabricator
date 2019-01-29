<?php

final class PhabricatorAuthFactorProviderListController
  extends PhabricatorAuthProviderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $can_manage = $this->hasApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    $providers = id(new PhabricatorAuthFactorProviderQuery())
      ->setViewer($viewer)
      ->execute();

    $list = new PHUIObjectItemListView();
    foreach ($providers as $provider) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($provider->getObjectName())
        ->setHeader($provider->getDisplayName())
        ->setHref($provider->getURI());

      $status = $provider->newStatus();

      $icon = $status->getListIcon();
      $color = $status->getListColor();
      if ($icon !== null) {
        $item->setStatusIcon("{$icon} {$color}", $status->getName());
      }

      $item->setDisabled(!$status->isActive());

      $list->addItem($item);
    }

    $list->setNoDataString(
      pht('You have not configured any multi-factor providers yet.'));

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Multi-Factor'))
      ->setBorder(true);

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE)
      ->setHref($this->getApplicationURI('mfa/edit/'))
      ->setIcon('fa-plus')
      ->setDisabled(!$can_manage)
      ->setWorkflow(true)
      ->setText(pht('Add MFA Provider'));

    $list->setFlush(true);
    $list = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('MFA Providers'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($list);

    $title = pht('MFA Providers');
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-mobile')
      ->addActionLink($button);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $list,
        ));

    $nav = $this->newNavigation()
      ->setCrumbs($crumbs)
      ->appendChild($view);

    $nav->selectFilter('mfa');

    return $this->newPage()
      ->setTitle($title)
      ->appendChild($nav);
  }

}
