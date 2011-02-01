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

class PhabricatorFacebookAuthController extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $auth_enabled = PhabricatorEnv::getEnvConfig('facebook.auth-enabled');
    if (!$auth_enabled) {
      return new Aphront400Response();
    }

    $diagnose_auth =
      '<a href="/facebook-auth/diagnose/" class="button green">'.
        'Diagnose Facebook Auth Problems'.
      '</a>';

    $request = $this->getRequest();

    if ($request->getStr('error')) {
      $view = new AphrontRequestFailureView();
      $view->setHeader('Facebook Auth Failed');
      $view->appendChild(
        '<p>'.
          '<strong>Description:</strong> '.
          phutil_escape_html($request->getStr('error_description')).
        '</p>');
      $view->appendChild(
        '<p>'.
          '<strong>Error:</strong> '.
          phutil_escape_html($request->getStr('error')).
        '</p>');
      $view->appendChild(
        '<p>'.
          '<strong>Error Reason:</strong> '.
          phutil_escape_html($request->getStr('error_reason')).
        '</p>');
      $view->appendChild(
        '<div class="aphront-failure-continue">'.
          '<a href="/login/" class="button">Continue</a>'.
        '</div>');

      return $this->buildStandardPageResponse(
        $view,
        array(
          'title' => 'Facebook Auth Failed',
        ));
    }

    $token = $request->getStr('token');
    if (!$token) {
      $app_id = PhabricatorEnv::getEnvConfig('facebook.application-id');
      $app_secret = PhabricatorEnv::getEnvConfig('facebook.application-secret');
      $redirect_uri = PhabricatorEnv::getURI('/facebook-auth/');

      $code = $request->getStr('code');
      $auth_uri = new PhutilURI(
        "https://graph.facebook.com/oauth/access_token");
      $auth_uri->setQueryParams(
        array(
          'client_id'     => $app_id,
          'redirect_uri'  => $redirect_uri,
          'client_secret' => $app_secret,
          'code'          => $code,
        ));

      $response = @file_get_contents($auth_uri);
      if ($response === false) {
        $view = new AphrontRequestFailureView();
        $view->setHeader('Facebook Auth Failed');
        $view->appendChild(
          '<p>Unable to authenticate with Facebook. There are several reasons '.
          'this might happen:</p>'.
            '<ul>'.
              '<li>Phabricator may be configured with the wrong Application '.
              'Secret; or</li>'.
              '<li>the Facebook OAuth access token may have expired; or</li>'.
              '<li>Facebook may have revoked authorization for the '.
              'Application; or</li>'.
              '<li>Facebook may be having technical problems.</li>'.
            '</ul>'.
          '<p>You can try again, or login using another method.</p>');
        $view->appendChild(
          '<div class="aphront-failure-continue">'.
            $diagnose_auth.
            '<a href="/login/" class="button">Continue</a>'.
          '</div>');

        return $this->buildStandardPageResponse(
          $view,
          array(
            'title' => 'Facebook Auth Failed',
          ));
      }

      $data = array();
      parse_str($response, $data);

      $token = $data['access_token'];
    }

    $user_json = @file_get_contents('https://graph.facebook.com/me?access_token='.$token);
    $user_data = json_decode($user_json, true);

    $user_id = $user_data['id'];

    $known_user = id(new PhabricatorUser())
      ->loadOneWhere('facebookUID = %d', $user_id);
    if ($known_user) {
      $session_key = $known_user->establishSession('web');
      $request->setCookie('phusr', $known_user->getUsername());
      $request->setCookie('phsid', $session_key);
      return id(new AphrontRedirectResponse())
        ->setURI('/');
    }
    
    $known_email = id(new PhabricatorUser())
      ->loadOneWhere('email = %s', $user_data['email']);
    if ($known_email) {
      if ($known_email->getFacebookUID()) {
        throw new Exception(
          "The email associated with the Facebook account you just logged in ".
          "with is already associated with another Phabricator account which ".
          "is, in turn, associated with a Facebook account different from ".
          "the one you just logged in with.");
      }
      $known_email->setFacebookUID($user_id);
      $session_key = $known_email->establishSession('web');
      $request->setCookie('phusr', $known_email->getUsername());
      $request->setCookie('phsid', $session_key);
      return id(new AphrontRedirectResponse())
        ->setURI('/');
    }

    $current_user = $this->getRequest()->getUser();
    if ($current_user->getPHID()) {
      if ($current_user->getFacebookUID() &&
          $current_user->getFacebookUID() != $user_id) {
        throw new Exception(
          "Your account is already associated with a Facebook user ID other ".
          "than the one you just logged in with...?");
      }

      if ($request->isFormPost()) {
        $current_user->setFacebookUID($user_id);
        $current_user->save();

        // TODO: ship them back to the 'account' page or whatever?
        return id(new AphrontRedirectResponse())
          ->setURI('/');
      }

      $ph_account = $current_user->getUsername();
      $fb_account = phutil_escape_html($user_data['name']);

      $form = new AphrontFormView();
      $form
        ->addHiddenInput('token', $token)
        ->setUser($request->getUser())
        ->setAction('/facebook-auth/')
        ->appendChild(
          '<p class="aphront-form-view-instructions">Do you want to link your '.
          "existing Phabricator account (<strong>{$ph_account}</strong>) ".
          "with your Facebook account (<strong>{$fb_account}</strong>) so ".
          "you can login with Facebook?")
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Link Accounts')
            ->addCancelButton('/login/'));

      $panel = new AphrontPanelView();
      $panel->setHeader('Link Facebook Account');
      $panel->setWidth(AphrontPanelView::WIDTH_FORM);
      $panel->appendChild($form);

      return $this->buildStandardPageResponse(
        $panel,
        array(
          'title' => 'Link Facebook Account',
        ));
    }

    $errors = array();
    $e_username = true;

    $user = new PhabricatorUser();

    $matches = null;
    if (preg_match('@/([a-zA-Z0-9]+)$@', $user_data['link'], $matches)) {
      $user->setUsername($matches[1]);
    }

    if ($request->isFormPost()) {

      $username = $request->getStr('username');
      if (!strlen($username)) {
        $e_username = 'Required';
        $errors[] = 'Username is required.';
      } else if (!preg_match('/^[a-zA-Z0-9]+$/', $username, $matches)) {
        $e_username = 'Invalid';
        $errors[] = 'Username may only contain letters and numbers.';
      }

      $user->setUsername($username);
      $user->setFacebookUID($user_id);
      $user->setEmail($user_data['email']);

      if (!$errors) {
        $image = @file_get_contents('https://graph.facebook.com/me/picture?access_token='.$token);
        $file = PhabricatorFile::newFromFileData(
          $image,
          array(
            'name' => 'fbprofile.jpg'
          ));

        $user->setProfileImagePHID($file->getPHID());
        $user->setRealName($user_data['name']);

        try {
          $user->save();

          $session_key = $user->establishSession('web');
          $request->setCookie('phusr', $user->getUsername());
          $request->setCookie('phsid', $session_key);
          return id(new AphrontRedirectResponse())->setURI('/');
        } catch (AphrontQueryDuplicateKeyException $exception) {
          $key = $exception->getDuplicateKey();
          if ($key == 'userName') {
            $e_username = 'Duplicate';
            $errors[] = 'That username is not unique.';
          } else {
            throw $exception;
          }
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Facebook Auth Failed');
      $error_view->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form
      ->addHiddenInput('token', $token)
      ->setUser($request->getUser())
      ->setAction('/facebook-auth/')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Username')
          ->setName('username')
          ->setValue($user->getUsername())
          ->setError($e_username))
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

}
