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

    if ($request->isFormPost() && $oauth_info) {
      $notice = $this->refreshProfileImage($request, $oauth_info);
    }

    $form = new AphrontFormView();
    $form->setUser($user);

    $forms = array();
    $forms[] = $form;
    if (!$oauth_info) {
      $form
        ->appendChild(
          '<p class="aphront-form-instructions">There is currently no '.
          phutil_escape_html($provider_name).' account linked to your '.
          'Phabricator account. You can link an account, which will allow you '.
          'to use it to log into Phabricator.</p>');

      $this->prepareAuthForm($form);

      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Link '.$provider_name." Account \xC2\xBB"));
    } else {
      $expires = $oauth_info->getTokenExpires();

      $form
        ->appendChild(
          '<p class="aphront-form-instructions">Your account is linked with '.
          'a '.phutil_escape_html($provider_name).' account. You may use your '.
          phutil_escape_html($provider_name).' credentials to log into '.
          'Phabricator.</p>')
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel($provider_name.' ID')
            ->setValue($oauth_info->getOAuthUID())
          )
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel($provider_name.' Name')
            ->setValue($oauth_info->getAccountName())
          )
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel($provider_name.' URI')
            ->setValue($oauth_info->getAccountURI())
          );

      if (!$expires || $expires > time()) {
        $form->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Refresh Profile Image from '.$provider_name)
          );
      }

      if (!$provider->isProviderLinkPermanent()) {
        $unlink = 'Unlink '.$provider_name.' Account';
        $unlink_form = new AphrontFormView();
        $unlink_form
          ->setUser($user)
          ->appendChild(
            '<p class="aphront-form-instructions">You may unlink this account '.
            'from your '.phutil_escape_html($provider_name).' account. This '.
            'will prevent you from logging in with your '.
            phutil_escape_html($provider_name).' credentials.</p>')
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->addCancelButton('/oauth/'.$provider_key.'/unlink/', $unlink));
        $forms['Unlink Account'] = $unlink_form;
      }

      if ($expires) {
        if ($expires <= time()) {
          $expires_text = "Expired";
        } else {
          $expires_text = phabricator_datetime($expires, $user);
        }
      } else {
        $expires_text = 'No Information Available';
      }

      $scope = $oauth_info->getTokenScope();
      if (!$scope) {
        $scope = 'No Information Available';
      }

      $status = $oauth_info->getTokenStatus();
      $status = PhabricatorUserOAuthInfo::getReadableTokenStatus($status);

      $token_form = new AphrontFormView();
      $token_form
        ->setUser($user)
        ->appendChild(
          '<p class="aphront-from-instructions">insert rap about tokens</p>')
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel('Token Status')
            ->setValue($status))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel('Expires')
            ->setValue($expires_text))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel('Scope')
            ->setValue($scope));

      if ($expires <= time()) {
        $this->prepareAuthForm($token_form);
        $token_form
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue('Refresh '.$provider_name.' Token')
            );
      }

      $forms['Account Token Information'] = $token_form;
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($provider_name.' Account Settings');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    foreach ($forms as $name => $form) {
      if ($name) {
        $panel->appendChild('<br /><h1>'.$name.'</h1><br />');
      }
      $panel->appendChild($form);
    }

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $notice,
          $panel,
        ));
  }

  private function refreshProfileImage(
    AphrontRequest $request,
    PhabricatorUserOAuthInfo $oauth_info) {

    $user         = $request->getUser();
    $provider     = $this->provider;
    $error        = false;
    $userinfo_uri = new PhutilURI($provider->getUserInfoURI());
    $token        = $oauth_info->getToken();
    try {
      $userinfo_uri->setQueryParam('access_token', $token);
      $user_data = HTTPSFuture::loadContent($userinfo_uri);
      $provider->setUserData($user_data);
      $provider->setAccessToken($token);
      $image = $provider->retrieveUserProfileImage();
      if ($image) {
        $file = PhabricatorFile::newFromFileData(
          $image,
          array(
            'name' => $provider->getProviderKey().'-profile.jpg',
            'authorPHID' => $user->getPHID(),
          ));

        $xformer = new PhabricatorImageTransformer();

        // Resize OAuth image to a reasonable size
        $small_xformed = $xformer->executeProfileTransform(
          $file,
          $width = 50,
          $min_height = 50,
          $max_height = 50);

        $user->setProfileImagePHID($small_xformed->getPHID());
        $user->save();
      } else {
        $error = 'Unable to retrieve image.';
      }
    } catch (Exception $e) {
      if ($e instanceof PhabricatorOAuthProviderException) {
        $error = sprintf('Unable to retrieve image from %s',
                         $provider->getProviderName());
      } else {
        $error = 'Unable to save image.';
      }
    }
    $notice = new AphrontErrorView();
    if ($error) {
      $notice
        ->setTitle('Error Refreshing Profile Picture')
        ->setErrors(array($error));
    } else {
      $notice
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle('Successfully Refreshed Profile Picture');
    }
    return $notice;
  }
}
