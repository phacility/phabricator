<?php

final class PhabricatorAuthListController
  extends PhabricatorAuthProviderConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->execute();

    $list = new PHUIObjectItemListView();
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

      $item
        ->setHeader($name);

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
      }

      if ($config->getIsEnabled()) {
        $item->setBarColor('green');
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('delete')
            ->setHref($disable_uri)
            ->addSigil('workflow'));
      } else {
        $item->setBarColor('grey');
        $item->addIcon('delete-grey', pht('Disabled'));
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('new')
            ->setHref($enable_uri)
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
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Auth Providers')));

    $config_name = 'auth.email-domains';
    $config_href = '/config/edit/'.$config_name.'/';
    $config_link = phutil_tag(
      'a',
      array(
        'href' => $config_href,
        'target' => '_blank',
      ),
      $config_name);

    $warning = new AphrontErrorView();

    $email_domains = PhabricatorEnv::getEnvConfig($config_name);
    if ($email_domains) {
      $warning->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $warning->setTitle(pht('Registration is Restricted'));
      $warning->appendChild(
        pht(
          'Only users with a verified email address at one of the %s domains '.
          'will be able to register a Phabricator account: %s',
          $config_link,
          phutil_tag('strong', array(), implode(', ', $email_domains))));
    } else {
      $warning->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $warning->setTitle(pht('Anyone Can Register an Account'));
      $warning->appendChild(
        pht(
          'Anyone who can browse to this Phabricator install will be able to '.
          'register an account. To restrict who can register an account, '.
          'configure %s.',
          $config_link));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $warning,
        $list,
      ),
      array(
        'title' => pht('Authentication Providers'),
        'device' => true,
      ));
  }

}
