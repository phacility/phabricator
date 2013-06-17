<?php

final class PhabricatorAuthLinkController
  extends PhabricatorAuthController {

  private $providerKey;

  public function willProcessRequest(array $data) {
    $this->providerKey = $data['pkey'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $provider = PhabricatorAuthProvider::getEnabledProviderByKey(
      $this->providerKey);
    if (!$provider) {
      return new Aphront404Response();
    }

    if (!$provider->shouldAllowAccountLink()) {
      return $this->renderErrorPage(
        pht('Account Not Linkable'),
        array(
          pht('This provider is not configured to allow linking.'),
        ));
    }

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountType = %s AND accountDomain = %s AND userPHID = %s',
      $provider->getProviderType(),
      $provider->getProviderDomain(),
      $viewer->getPHID());
    if ($account) {
      return $this->renderErrorPage(
        pht('Account Already Linked'),
        array(
          pht(
            'Your Phabricator account is already linked to an external '.
            'account for this provider.'),
        ));
    }

    $panel_uri = '/settings/panel/external/';

    $request->setCookie('phcid', Filesystem::readRandomCharacters(16));
    $form = $provider->buildLinkForm($this);

    if ($provider->isLoginFormAButton()) {
      require_celerity_resource('auth-css');
      $form = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-link-button pl',
        ),
        $form);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Link Account'))
        ->setHref($panel_uri));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($provider->getProviderName()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form,
      ),
      array(
        'title' => pht('Link %s Account', $provider->getProviderName()),
        'dust' => true,
        'device' => true,
      ));
  }

}
