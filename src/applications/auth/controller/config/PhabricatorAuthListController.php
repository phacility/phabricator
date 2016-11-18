<?php

final class PhabricatorAuthListController
  extends PhabricatorAuthProviderConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->execute();

    $list = new PHUIObjectItemListView();
    $can_manage = $this->hasApplicationCapability(
        AuthManageProvidersCapability::CAPABILITY);

    foreach ($configs as $config) {
      $item = new PHUIObjectItemView();

      $id = $config->getID();

      $edit_uri = $this->getApplicationURI('config/edit/'.$id.'/');
      $enable_uri = $this->getApplicationURI('config/enable/'.$id.'/');
      $disable_uri = $this->getApplicationURI('config/disable/'.$id.'/');

      $provider = $config->getProvider();
      if ($provider) {
        $name = $provider->getProviderName();
      } else {
        $name = $config->getProviderType().' ('.$config->getProviderClass().')';
      }

      $item->setHeader($name);

      if ($provider) {
        $item->setHref($edit_uri);
      } else {
        $item->addAttribute(pht('Provider Implementation Missing!'));
      }

      $domain = null;
      if ($provider) {
        $domain = $provider->getProviderDomain();
        if ($domain !== 'self') {
          $item->addAttribute($domain);
        }
      }

      if ($config->getShouldAllowRegistration()) {
        $item->addAttribute(pht('Allows Registration'));
      } else {
        $item->addAttribute(pht('Does Not Allow Registration'));
      }

      if ($config->getIsEnabled()) {
        $item->setStatusIcon('fa-check-circle green');
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-times')
            ->setHref($disable_uri)
            ->setDisabled(!$can_manage)
            ->addSigil('workflow'));
      } else {
        $item->setStatusIcon('fa-ban red');
        $item->addIcon('fa-ban grey', pht('Disabled'));
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-plus')
            ->setHref($enable_uri)
            ->setDisabled(!$can_manage)
            ->addSigil('workflow'));
      }

      $list->addItem($item);
    }

    $list->setNoDataString(
      pht(
        '%s You have not added authentication providers yet. Use "%s" to add '.
        'a provider, which will let users register new Phabricator accounts '.
        'and log in.',
        phutil_tag(
          'strong',
          array(),
          pht('No Providers Configured:')),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI('config/new/'),
          ),
          pht('Add Authentication Provider'))));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Auth Providers'));
    $crumbs->setBorder(true);

    $guidance_context = new PhabricatorAuthProvidersGuidanceContext();

    $guidance = id(new PhabricatorGuidanceEngine())
      ->setViewer($viewer)
      ->setGuidanceContext($guidance_context)
      ->newInfoView();

    $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::SIMPLE)
        ->setHref($this->getApplicationURI('config/new/'))
        ->setIcon('fa-plus')
        ->setDisabled(!$can_manage)
        ->setText(pht('Add Provider'));

    $list->setFlush(true);
    $list = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Providers'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($list);

    $title = pht('Auth Providers');
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-key')
      ->addActionLink($button);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $guidance,
        $list,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
