<?php

final class PhabricatorSettingsPanelOAuth
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'oauth-'.$this->provider->getProviderKey();
  }

  public function getPanelName() {
    return $this->provider->getProviderName();
  }

  public function getPanelGroup() {
    return pht('Linked Accounts');
  }

  public function buildPanels() {
    $panels = array();

    $providers = PhabricatorOAuthProvider::getAllProviders();
    foreach ($providers as $provider) {
      $panel = clone $this;
      $panel->setOAuthProvider($provider);
      $panels[] = $panel;
    }

    return $panels;
  }

  public function isEnabled() {
    return $this->provider->isProviderEnabled();
  }

  private $provider;

  public function setOAuthProvider(PhabricatorOAuthProvider $oauth_provider) {
    $this->provider = $oauth_provider;
    return $this;
  }

  private function prepareAuthForm(AphrontFormView $form) {
    $provider = $this->provider;

    $auth_uri = $provider->getAuthURI();
    $client_id = $provider->getClientID();
    $redirect_uri = $provider->getRedirectURI();
    $minimum_scope = $provider->getMinimumScope();

    $form
      ->setAction($auth_uri)
      ->setMethod('GET')
      ->addHiddenInput('redirect_uri', $redirect_uri)
      ->addHiddenInput('client_id', $client_id)
      ->addHiddenInput('scope', $minimum_scope);

    foreach ($provider->getExtraAuthParameters() as $key => $value) {
      $form->addHiddenInput($key, $value);
    }

    return $form;
  }

  public function processRequest(AphrontRequest $request) {
    $user          = $request->getUser();
    $provider      = $this->provider;
    $notice        = null;
    $provider_name = $provider->getProviderName();
    $provider_key  = $provider->getProviderKey();

    $oauth_info = id(new PhabricatorUserOAuthInfo())->loadOneWhere(
      'userID = %d AND oauthProvider = %s',
      $user->getID(),
      $provider->getProviderKey());

    $form = new AphrontFormView();
    $form->setUser($user);

    $forms = array();
    $forms[] = $form;
    if (!$oauth_info) {
      $form
        ->appendChild(hsprintf(
          '<p class="aphront-form-instructions">%s</p>',
          pht('There is currently no %s '.
            'account linked to your Phabricator account. You can link an '.
            'account, which will allow you to use it to log into Phabricator.',
            $provider_name)));

      $this->prepareAuthForm($form);

      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht("Link %s Account \xC2\xBB", $provider_name)));
    } else {
      $form
        ->appendChild(hsprintf(
          '<p class="aphront-form-instructions">%s</p>',
          pht('Your account is linked with '.
            'a %s account. You may use your %s credentials to log into '.
            'Phabricator.',
            $provider_name,
            $provider_name)))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('%s ID', $provider_name))
            ->setValue($oauth_info->getOAuthUID()))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('%s Name', $provider_name))
            ->setValue($oauth_info->getAccountName()))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('%s URI', $provider_name))
            ->setValue($oauth_info->getAccountURI()));

      if (!$provider->isProviderLinkPermanent()) {
        $unlink = pht('Unlink %s Account', $provider_name);
        $unlink_form = new AphrontFormView();
        $unlink_form
          ->setUser($user)
          ->appendChild(hsprintf(
            '<p class="aphront-form-instructions">%s</p>',
            pht('You may unlink this account from your %s account. This will '.
              'prevent you from logging in with your %s credentials.',
              $provider_name,
              $provider_name)))
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->addCancelButton('/oauth/'.$provider_key.'/unlink/', $unlink));
        $forms['Unlink Account'] = $unlink_form;
      }
    }

    $header = new PhabricatorHeaderView();
    $header->setHeader(pht('%s Account Settings', $provider_name));

    $formbox = new PHUIBoxView();
    foreach ($forms as $name => $form) {
      if ($name) {
        $head = new PhabricatorHeaderView();
        $head->setHeader($name);
        $formbox->appendChild($head);
      }
      $formbox->appendChild($form);
    }

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $notice,
          $header,
          $formbox,
        ));
  }
}
