<?php

final class PhabricatorAuthLinkController
  extends PhabricatorAuthController {

  private $action;
  private $providerKey;

  public function willProcessRequest(array $data) {
    $this->providerKey = $data['pkey'];
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $provider = PhabricatorAuthProvider::getEnabledProviderByKey(
      $this->providerKey);
    if (!$provider) {
      return new Aphront404Response();
    }

    switch ($this->action) {
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

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountType = %s AND accountDomain = %s AND userPHID = %s',
      $provider->getProviderType(),
      $provider->getProviderDomain(),
      $viewer->getPHID());

    switch ($this->action) {
      case 'link':
        if ($account) {
          return $this->renderErrorPage(
            pht('Account Already Linked'),
            array(
              pht(
                'Your Phabricator account is already linked to an external '.
                'account for this provider.'),
            ));
        }
        break;
      case 'refresh':
        if (!$account) {
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

    switch ($this->action) {
      case 'link':
        id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
          $viewer,
          $request,
          $panel_uri);

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

    switch ($this->action) {
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

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form,
      ),
      array(
        'title' => $title,
      ));
  }

}
