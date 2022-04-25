<?php

final class PhabricatorAuthLinkController
  extends PhabricatorAuthController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $action = $request->getURIData('action');

    $id = $request->getURIData('id');

    $config = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->withIsEnabled(true)
      ->executeOne();
    if (!$config) {
      return new Aphront404Response();
    }

    $provider = $config->getProvider();

    switch ($action) {
      case 'link':
        if (!$provider->shouldAllowAccountLink()) {
          return $this->renderErrorPage(
            pht('Account Not Linkable'),
            array(
              pht('This provider is not configured to allow linking.'),
            ));
        }
        break;
      case 'refresh':
        if (!$provider->shouldAllowAccountRefresh()) {
          return $this->renderErrorPage(
            pht('Account Not Refreshable'),
            array(
              pht('This provider does not allow refreshing.'),
            ));
        }
        break;
      default:
        return new Aphront400Response();
    }

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withProviderConfigPHIDs(array($config->getPHID()))
      ->execute();

    switch ($action) {
      case 'link':
        if ($accounts) {
          return $this->renderErrorPage(
            pht('Account Already Linked'),
            array(
              pht(
                'Your account is already linked to an external account for '.
                'this provider.'),
            ));
        }
        break;
      case 'refresh':
        if (!$accounts) {
          return $this->renderErrorPage(
            pht('No Account Linked'),
            array(
              pht(
                'You do not have a linked account on this provider, and thus '.
                'can not refresh it.'),
            ));
        }
        break;
      default:
        return new Aphront400Response();
    }

    $panel_uri = '/settings/panel/external/';

    PhabricatorCookies::setClientIDCookie($request);

    switch ($action) {
      case 'link':
        $form = $provider->buildLinkForm($this);
        break;
      case 'refresh':
        $form = $provider->buildRefreshForm($this);
        break;
      default:
        return new Aphront400Response();
    }

    if ($provider->isLoginFormAButton()) {
      require_celerity_resource('auth-css');
      $form = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-link-button pl',
        ),
        $form);
    }

    switch ($action) {
      case 'link':
        $name = pht('Link Account');
        $title = pht('Link %s Account', $provider->getProviderName());
        break;
      case 'refresh':
        $name = pht('Refresh Account');
        $title = pht('Refresh %s Account', $provider->getProviderName());
        break;
      default:
        return new Aphront400Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Link Account'), $panel_uri);
    $crumbs->addTextCrumb($provider->getProviderName($name));
    $crumbs->setBorder(true);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($form);
  }

}
