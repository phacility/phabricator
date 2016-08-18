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

    $domains_key = 'auth.email-domains';
    $domains_link = $this->renderConfigLink($domains_key);
    $domains_value = PhabricatorEnv::getEnvConfig($domains_key);

    $approval_key = 'auth.require-approval';
    $approval_link = $this->renderConfigLink($approval_key);
    $approval_value = PhabricatorEnv::getEnvConfig($approval_key);

    $issues = array();
    if ($domains_value) {
      $issues[] = pht(
        'Phabricator is configured with an email domain whitelist (in %s), so '.
        'only users with a verified email address at one of these %s '.
        'allowed domain(s) will be able to register an account: %s',
        $domains_link,
        phutil_count($domains_value),
        phutil_tag('strong', array(), implode(', ', $domains_value)));
    } else {
      $issues[] = pht(
        'Anyone who can browse to this Phabricator install will be able to '.
        'register an account. To add email domain restrictions, configure '.
        '%s.',
        $domains_link);
    }

    if ($approval_value) {
      $issues[] = pht(
        'Administrative approvals are enabled (in %s), so all new users must '.
        'have their accounts approved by an administrator.',
        $approval_link);
    } else {
      $issues[] = pht(
        'Administrative approvals are disabled, so users who register will '.
        'be able to use their accounts immediately. To enable approvals, '.
        'configure %s.',
        $approval_link);
    }

    if (!$domains_value && !$approval_value) {
      $severity = PHUIInfoView::SEVERITY_WARNING;
      $issues[] = pht(
        'You can safely ignore this warning if the install itself has '.
        'access controls (for example, it is deployed on a VPN) or if all of '.
        'the configured providers have access controls (for example, they are '.
        'all private LDAP or OAuth servers).');
    } else {
      $severity = PHUIInfoView::SEVERITY_NOTICE;
    }

    $warning = id(new PHUIInfoView())
      ->setSeverity($severity)
      ->setErrors($issues);

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
        $warning,
        $list,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function renderConfigLink($key) {
    return phutil_tag(
      'a',
      array(
        'href' => '/config/edit/'.$key.'/',
        'target' => '_blank',
      ),
      $key);
  }

}
