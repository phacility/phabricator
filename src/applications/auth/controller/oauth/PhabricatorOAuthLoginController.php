<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorOAuthLoginController extends PhabricatorAuthController {

  private $provider;
  private $userID;
  private $accessToken;
  private $tokenExpires;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->provider = PhabricatorOAuthProvider::newProvider($data['provider']);
  }

  public function processRequest() {
    $current_user = $this->getRequest()->getUser();

    $provider = $this->provider;
    if (!$provider->isProviderEnabled()) {
      return new Aphront400Response();
    }

    $provider_name = $provider->getProviderName();
    $provider_key = $provider->getProviderKey();

    $request = $this->getRequest();

    if ($request->getStr('error')) {
      $error_view = id(new PhabricatorOAuthFailureView())
        ->setRequest($request);
      return $this->buildErrorResponse($error_view);
    }

    $token = $request->getStr('token');
    if (!$token) {
      $client_id        = $provider->getClientID();
      $client_secret    = $provider->getClientSecret();
      $redirect_uri     = $provider->getRedirectURI();
      $auth_uri         = $provider->getTokenURI();

      $code = $request->getStr('code');
      $query_data = array(
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $redirect_uri,
        'code'          => $code,
      );

      $stream_context = stream_context_create(
        array(
          'http' => array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($query_data),
          ),
        ));

      $stream = fopen($auth_uri, 'r', false, $stream_context);

      $meta = stream_get_meta_data($stream);
      $response = stream_get_contents($stream);

      fclose($stream);

      if ($response === false) {
        return $this->buildErrorResponse(new PhabricatorOAuthFailureView());
      }

      $data = array();
      parse_str($response, $data);

      $token = idx($data, 'access_token');
      if (!$token) {
        return $this->buildErrorResponse(new PhabricatorOAuthFailureView());
      }

      if (idx($data, 'expires')) {
        $this->tokenExpires = time() + $data['expires'];
      }

    } else {
      $this->tokenExpires = $request->getInt('expires');
    }

    $userinfo_uri = new PhutilURI($provider->getUserInfoURI());
    $userinfo_uri->setQueryParams(
      array(
        'access_token' => $token,
      ));

    $user_json = @file_get_contents($userinfo_uri);
    $user_data = json_decode($user_json, true);

    $this->accessToken = $token;

    switch ($provider->getProviderKey()) {
      case PhabricatorOAuthProvider::PROVIDER_GITHUB:
        $user_data = $user_data['user'];
        break;
    }
    $this->userData = $user_data;

    $user_id = $this->retrieveUserID();

    $known_oauth = id(new PhabricatorUserOAuthInfo())->loadOneWhere(
      'oauthProvider = %s and oauthUID = %s',
      $provider->getProviderKey(),
      $user_id);

    if ($current_user->getPHID()) {

      if ($known_oauth) {
        if ($known_oauth->getUserID() != $current_user->getID()) {
          $dialog = new AphrontDialogView();
          $dialog->setUser($current_user);
          $dialog->setTitle('Already Linked to Another Account');
          $dialog->appendChild(
            '<p>The '.$provider_name.' account you just authorized '.
            'is already linked to another Phabricator account. Before you can '.
            'associate your '.$provider_name.' account with this Phabriactor '.
            'account, you must unlink it from the Phabricator account it is '.
            'currently linked to.</p>');
          $dialog->addCancelButton('/settings/page/'.$provider_key.'/');

          return id(new AphrontDialogResponse())->setDialog($dialog);
        } else {
          return id(new AphrontRedirectResponse())
            ->setURI('/settings/page/'.$provider_key.'/');
        }
      }

      if (!$request->isDialogFormPost()) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($current_user);
        $dialog->setTitle('Link '.$provider_name.' Account');
        $dialog->appendChild(
          '<p>Link your '.$provider_name.' account to your Phabricator '.
          'account?</p>');
        $dialog->addHiddenInput('token', $token);
        $dialog->addHiddenInput('expires', $this->tokenExpires);
        $dialog->addSubmitButton('Link Accounts');
        $dialog->addCancelButton('/settings/page/'.$provider_key.'/');

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }

      $oauth_info = new PhabricatorUserOAuthInfo();
      $oauth_info->setUserID($current_user->getID());
      $this->configureOAuthInfo($oauth_info);
      $oauth_info->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/settings/page/'.$provider_key.'/');
    }


    // Login with known auth.

    if ($known_oauth) {
      $known_user = id(new PhabricatorUser())->load($known_oauth->getUserID());
      $session_key = $known_user->establishSession('web');

      $this->configureOAuthInfo($known_oauth);
      $known_oauth->save();

      $request->setCookie('phusr', $known_user->getUsername());
      $request->setCookie('phsid', $session_key);
      return id(new AphrontRedirectResponse())
        ->setURI('/');
    }

    // Merge accounts based on shared email. TODO: should probably get rid of
    // this.

    $oauth_email = $this->retrieveUserEmail();
    if ($oauth_email) {
      $known_email = id(new PhabricatorUser())
        ->loadOneWhere('email = %s', $oauth_email);
      if ($known_email) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($current_user);
        $dialog->setTitle('Already Linked to Another Account');
        $dialog->appendChild(
          '<p>The '.$provider_name.' account you just authorized has an '.
          'email address which is already in use by another Phabricator '.
          'account. To link the accounts, log in to your Phabricator '.
          'account and then go to Settings.</p>');
        $dialog->addCancelButton('/login/');

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }
    }

    $errors = array();
    $e_username = true;
    $e_email = true;
    $e_realname = true;

    $user = new PhabricatorUser();

    $suggestion = $this->retrieveUsernameSuggestion();
    $user->setUsername($suggestion);

    $oauth_realname = $this->retreiveRealNameSuggestion();

    if ($request->isFormPost()) {

      $user->setUsername($request->getStr('username'));
      $username = $user->getUsername();
      $matches = null;
      if (!strlen($user->getUsername())) {
        $e_username = 'Required';
        $errors[] = 'Username is required.';
      } else if (!preg_match('/^[a-zA-Z0-9]+$/', $username, $matches)) {
        $e_username = 'Invalid';
        $errors[] = 'Username may only contain letters and numbers.';
      } else {
        $e_username = null;
      }

      if ($oauth_email) {
        $user->setEmail($oauth_email);
      } else {
        $user->setEmail($request->getStr('email'));
        if (!strlen($user->getEmail())) {
          $e_email = 'Required';
          $errors[] = 'Email is required.';
        } else {
          $e_email = null;
        }
      }

      if ($oauth_realname) {
        $user->setRealName($oauth_realname);
      } else {
        $user->setRealName($request->getStr('realname'));
        if (!strlen($user->getStr('realname'))) {
          $e_realname = 'Required';
          $errors[] = 'Real name is required.';
        } else {
          $e_realname = null;
        }
      }

      if (!$errors) {
        $image = $this->retreiveProfileImageSuggestion();
        if ($image) {
          $file = PhabricatorFile::newFromFileData(
            $image,
            array(
              'name' => $provider->getProviderKey().'-profile.jpg'
            ));
          $user->setProfileImagePHID($file->getPHID());
        }

        try {
          $user->save();

          $oauth_info = new PhabricatorUserOAuthInfo();
          $oauth_info->setUserID($user->getID());
          $this->configureOAuthInfo($oauth_info);
          $oauth_info->save();

          $session_key = $user->establishSession('web');
          $request->setCookie('phusr', $user->getUsername());
          $request->setCookie('phsid', $session_key);
          return id(new AphrontRedirectResponse())->setURI('/');
        } catch (AphrontQueryDuplicateKeyException $exception) {
          $key = $exception->getDuplicateKey();
          if ($key == 'userName') {
            $e_username = 'Duplicate';
            $errors[] = 'That username is not unique.';
          } else if ($key == 'email') {
            $e_email = 'Duplicate';
            $errors[] = 'That email is not unique.';
          } else {
            throw $exception;
          }
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Registration Failed');
      $error_view->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form
      ->addHiddenInput('token', $token)
      ->addHiddenInput('expires', $this->tokenExpires)
      ->setUser($request->getUser())
      ->setAction($provider->getRedirectURI())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Username')
          ->setName('username')
          ->setValue($user->getUsername())
          ->setError($e_username));

    if (!$oauth_email) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Email')
          ->setName('email')
          ->setValue($request->getStr('email'))
          ->setError($e_email));
    }

    if (!$oauth_realname) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Real Name')
          ->setName('realname')
          ->setValue($request->getStr('realname'))
          ->setError($e_realname));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Create Account'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Create New Account');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Create New Account',
      ));
  }

  private function buildErrorResponse(PhabricatorOAuthFailureView $view) {
    $provider = $this->provider;

    $provider_name = $provider->getProviderName();
    $view->setOAuthProvider($provider);

    return $this->buildStandardPageResponse(
      $view,
      array(
        'title' => $provider_name.' Auth Failed',
      ));
  }

  private function retrieveUserID() {
    return $this->userData['id'];
  }

  private function retrieveUserEmail() {
    return $this->userData['email'];
  }

  private function retrieveUsernameSuggestion() {
    switch ($this->provider->getProviderKey()) {
      case PhabricatorOAuthProvider::PROVIDER_FACEBOOK:
        $matches = null;
        $link = $this->userData['link'];
        if (preg_match('@/([a-zA-Z0-9]+)$@', $link, $matches)) {
          return $matches[1];
        }
        break;
      case PhabricatorOAuthProvider::PROVIDER_GITHUB:
        return $this->userData['login'];
    }
    return null;
  }

  private function retreiveProfileImageSuggestion() {
    switch ($this->provider->getProviderKey()) {
      case PhabricatorOAuthProvider::PROVIDER_FACEBOOK:
        $uri = 'https://graph.facebook.com/me/picture?access_token=';
        return @file_get_contents($uri.$this->accessToken);
      case PhabricatorOAuthProvider::PROVIDER_GITHUB:
        $id = $this->userData['gravatar_id'];
        if ($id) {
          $uri = 'http://www.gravatar.com/avatar/'.$id.'?s=50';
          return @file_get_contents($uri);
        }
    }
    return null;
  }

  private function retrieveAccountURI() {
    switch ($this->provider->getProviderKey()) {
      case PhabricatorOAuthProvider::PROVIDER_FACEBOOK:
        return $this->userData['link'];
      case PhabricatorOAuthProvider::PROVIDER_GITHUB:
        $username = $this->retrieveUsernameSuggestion();
        if ($username) {
          return 'https://github.com/'.$username;
        }
        return null;
    }
    return null;
  }

  private function retreiveRealNameSuggestion() {
    return $this->userData['name'];
  }

  private function configureOAuthInfo(PhabricatorUserOAuthInfo $oauth_info) {
    $provider = $this->provider;

    $oauth_info->setOAuthProvider($provider->getProviderKey());
    $oauth_info->setOAuthUID($this->retrieveUserID());
    $oauth_info->setAccountURI($this->retrieveAccountURI());
    $oauth_info->setAccountName($this->retrieveUserNameSuggestion());

    $oauth_info->setToken($this->accessToken);
    $oauth_info->setTokenStatus(PhabricatorUserOAuthInfo::TOKEN_STATUS_GOOD);

    // If we have out-of-date expiration info, just clear it out. Then replace
    // it with good info if the provider gave it to us.
    $expires = $oauth_info->getTokenExpires();
    if ($expires <= time()) {
      $expires = null;
    }
    if ($this->tokenExpires) {
      $expires = $this->tokenExpires;
    }
    $oauth_info->setTokenExpires($expires);
  }

}
