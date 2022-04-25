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
    $is_locked = PhabricatorEnv::getEnvConfig('auth.lock-config');

    foreach ($configs as $config) {
      $item = new PHUIObjectItemView();

      $id = $config->getID();

      $view_uri = $config->getURI();

      $provider = $config->getProvider();
      $name = $provider->getProviderName();

      $item
        ->setHeader($name)
        ->setHref($view_uri);

      $domain = $provider->getProviderDomain();
      if ($domain !== 'self') {
        $item->addAttribute($domain);
      }

      if ($config->getShouldAllowRegistration()) {
        $item->addAttribute(pht('Allows Registration'));
      } else {
        $item->addAttribute(pht('Does Not Allow Registration'));
      }

      if ($config->getIsEnabled()) {
        $item->setStatusIcon('fa-check-circle green');
      } else {
        $item->setStatusIcon('fa-ban red');
        $item->addIcon('fa-ban grey', pht('Disabled'));
      }

      $list->addItem($item);
    }

    $list->setNoDataString(
      pht(
        '%s You have not added authentication providers yet. Use "%s" to add '.
        'a provider, which will let users register new accounts and log in.',
        phutil_tag(
          'strong',
          array(),
          pht('No Providers Configured:')),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI('config/new/'),
          ),
          pht('Add Provider'))));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Login and Registration'));
    $crumbs->setBorder(true);

    $guidance_context = id(new PhabricatorAuthProvidersGuidanceContext())
      ->setCanManage($can_manage);

    $guidance = id(new PhabricatorGuidanceEngine())
      ->setViewer($viewer)
      ->setGuidanceContext($guidance_context)
      ->newInfoView();

    $is_disabled = (!$can_manage || $is_locked);
    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE)
      ->setIcon('fa-plus')
      ->setDisabled($is_disabled)
      ->setWorkflow($is_disabled)
      ->setHref($this->getApplicationURI('config/new/'))
      ->setText(pht('Add Provider'));

    $list->setFlush(true);
    $list = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Providers'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($list);

    $title = pht('Login and Registration Providers');
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-key')
      ->addActionLink($button);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $guidance,
          $list,
        ));

    $nav = $this->newNavigation()
      ->setCrumbs($crumbs)
      ->appendChild($view);

    $nav->selectFilter('login');

    return $this->newPage()
      ->setTitle($title)
      ->appendChild($nav);
  }

}
