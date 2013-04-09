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
      $expires = $oauth_info->getTokenExpires();

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

      if (!$expires || $expires > time()) {
        $form->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Refresh Profile Image from %s', $provider_name)));
      }

      if (!$provider->isProviderLinkPermanent()) {
        $unlink = pht('Unlink %s Account');
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

      if ($expires) {
        if ($expires <= time()) {
          $expires_text = pht("Expired");
        } else {
          $expires_text = phabricator_datetime($expires, $user);
        }
      } else {
        $expires_text = pht('No Information Available');
      }

      $scope = $oauth_info->getTokenScope();
      if (!$scope) {
        $scope = pht('No Information Available');
      }

      $status = $oauth_info->getTokenStatus();
      $readable_status = PhabricatorUserOAuthInfo::getReadableTokenStatus(
        $status);
      $rappable_status = PhabricatorUserOAuthInfo::getRappableTokenStatus(
        $status);
      $beat = self::getBeat();

      // The plenty %2$s are supposed to point at the line break
      $rap = pht(
        '%1$s Yo yo yo %2$s'.
        'My name\'s DJ Token and I\'m here to say %2$s'.
        // pronounce as "dollar rappable status" for meter to work
        '%3$s, hey hey hey hey %2$s'.
        'I rap \'bout tokens, that might be why %2$s'.
        'I\'m such a cool and popular guy',
        $beat,
        hsprintf('<br />'),
        $rappable_status);

      $token_form = new AphrontFormView();
      $token_form
        ->setUser($user)
        ->appendChild(hsprintf(
          '<p class="aphront-form-instructions">%s</p>',
          $rap))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('Token Status'))
            ->setValue($readable_status))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('Expires'))
            ->setValue($expires_text))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('Scope'))
            ->setValue($scope));

      if ($expires <= time()) {
        $this->prepareAuthForm($token_form);
        $token_form
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue(pht('Refresh %s Token', $provider_name)));
      }

      $forms['Account Token Information'] = $token_form;
    }

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('%s Account Settings', $provider_name));
    $panel->setNoBackground();

    foreach ($forms as $name => $form) {
      if ($name) {
        $panel->appendChild(hsprintf('<br /><h1>%s</h1><br />', $name));
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
        $error = pht('Unable to retrieve image.');
      }
    } catch (Exception $e) {
      if ($e instanceof PhabricatorOAuthProviderException) {
        // Check plz
        $error = pht('Unable to retrieve image from %s',
                         $provider->getProviderName());
      } else {
        $error = pht('Unable to save image.');
      }
    }
    $notice = new AphrontErrorView();
    if ($error) {
      $notice
        ->setTitle(pht('Error Refreshing Profile Picture'))
        ->setErrors(array($error));
    } else {
      $notice
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(pht('Successfully Refreshed Profile Picture'));
    }
    return $notice;
  }

  private static function getBeat() {
    // Gangsta's Paradise (karaoke version).
    // Chosen because it's the only thing I listen to.
    $song_id = pht("Gangsta's Paradise");

    // Make a musical note which you can click for the beat.
    $beat = hsprintf(
      '<a href="javascript:void(0);" onclick="%s">&#9835;</a>',
      jsprintf('alert(%s); return 0;', pht("Think about %s.", $song_id)));
    return $beat;
  }
}
