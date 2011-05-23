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

class PhabricatorUserSettingsController extends PhabricatorPeopleController {

  private $page;
  private $accountEditable;

  public function willProcessRequest(array $data) {
    $this->page = idx($data, 'page');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $pages = array(
      'account'     => 'Account',
      'email'       => 'Email',
//      'password'    => 'Password',
      'arcanist'    => 'Arcanist Certificate',
    );

    $oauth_providers = PhabricatorOAuthProvider::getAllProviders();
    foreach ($oauth_providers as $provider) {
      if (!$provider->isProviderEnabled()) {
        continue;
      }
      $key = $provider->getProviderKey();
      $name = $provider->getProviderName();
      $pages[$key] = $name.' Account';
    }

    if (empty($pages[$this->page])) {
      $this->page = key($pages);
    }

    $account_editable = PhabricatorEnv::getEnvConfig('account.editable');
    $this->accountEditable = $account_editable;

    $e_realname = true;
    $e_email = true;
    $errors = array();

    if ($request->isFormPost()) {
      switch ($this->page) {
        case 'email':
          if (!$account_editable) {
            return new Aphront400Response();
          }

          $user->setEmail($request->getStr('email'));

          if (!strlen($user->getEmail())) {
            $errors[] = 'You must enter an e-mail address';
            $e_email = 'Required';
          }

          if (!$errors) {
            $user->save();

            return id(new AphrontRedirectResponse())
              ->setURI('/settings/page/email/?saved=true');
          }
          break;
        case 'arcanist':

          if (!$request->isDialogFormPost()) {
            $dialog = new AphrontDialogView();
            $dialog->setUser($user);
            $dialog->setTitle('Really regenerate session?');
            $dialog->setSubmitURI('/settings/page/arcanist/');
            $dialog->addSubmitButton('Regenerate');
            $dialog->addCancelbutton('/settings/page/arcanist/');
            $dialog->appendChild(
              '<p>Really destroy the old certificate? Any established '.
              'sessions will be terminated.');

            return id(new AphrontDialogResponse())
              ->setDialog($dialog);
          }

          $conn = $user->establishConnection('w');
          queryfx(
            $conn,
            'DELETE FROM %T WHERE userPHID = %s AND type LIKE %>',
            PhabricatorUser::SESSION_TABLE,
            $user->getPHID(),
            'conduit');
          // This implicitly regenerates the certificate.
          $user->setConduitCertificate(null);
          $user->save();
          return id(new AphrontRedirectResponse())
            ->setURI('/settings/page/arcanist/?regenerated=true');
          break;
        case 'account':
          if (!$account_editable) {
            return new Aphront400Response();
          }

          if (!empty($_FILES['profile'])) {
            $err = idx($_FILES['profile'], 'error');
            if ($err != UPLOAD_ERR_NO_FILE) {
              $file = PhabricatorFile::newFromPHPUpload($_FILES['profile']);
              $user->setProfileImagePHID($file->getPHID());
            }
          }

          $user->setRealName($request->getStr('realname'));

          if (!strlen($user->getRealName())) {
            $errors[] = 'Real name must be nonempty';
            $e_realname = 'Required';
          }

          if (!$errors) {
            $user->save();

            return id(new AphrontRedirectResponse())
                ->setURI('/settings/page/account/?saved=true');
          }
          break;
      }
    }


    switch ($this->page) {
      case 'arcanist':
        $content = $this->renderArcanistCertificateForm();
        break;
      case 'account':
        $content = $this->renderAccountForm($errors, $e_realname);
        break;
      case 'email':
        $content = $this->renderEmailForm($errors, $e_email);
        break;
      default:
        if (empty($pages[$this->page])) {
          return new Aphront404Response();
        }
        $content = $this->renderOAuthForm($oauth_providers[$this->page]);
        break;
    }


    $sidenav = new AphrontSideNavView();
    foreach ($pages as $page => $name) {
      $sidenav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/settings/page/'.$page.'/',
            'class' => ($page == $this->page)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($name)));
    }

    $sidenav->appendChild($content);

    return $this->buildStandardPageResponse(
      $sidenav,
      array(
        'title' => 'Account Settings',
      ));
  }

  private function renderArcanistCertificateForm() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->getStr('regenerated')) {
      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $notice->setTitle('Certificate Regenerated');
      $notice->appendChild(
        '<p>Your old certificate has been destroyed and you have been issued '.
        'a new certificate. Sessions established under the old certificate '.
        'are no longer valid.</p>');
      $notice = $notice->render();
    } else {
      $notice = null;
    }

    $host = PhabricatorEnv::getEnvConfig('phabricator.base-uri') . 'api/';
    $conduit_setting = sprintf(
      '    %s: {'."\n".
      '      "user" : %s,'."\n".
      '      "cert" : %s'."\n".
      '    }'."\n",
      json_encode($host),
      json_encode($user->getUserName()),
      json_encode($user->getConduitCertificate()));

    $cert_form = new AphrontFormView();
    $cert_form
      ->setUser($user)
      ->appendChild(
        '<p class="aphront-form-instructions">Copy and paste the host info '.
        'including the certificate into your <tt>~/.arcrc</tt> in the "hosts" '.
        'session to enable Arcanist to authenticate against this host.</p>')
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setControlStyle('white-space: pre; font-family: monospace')
          ->setValue(
            '{'."\n".
            '  ...'."\n".
            '  "hosts" : {'."\n"))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Credentials')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_SHORT)
          ->setControlStyle('font-family: monospace')
          ->setValue($conduit_setting))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setControlStyle('white-space: pre; font-family: monospace')
          ->setValue(
            '  }'."\n".
            '  ...'."\n".
            '}'));

    $cert = new AphrontPanelView();
    $cert->setHeader('Arcanist Certificate');
    $cert->appendChild($cert_form);
    $cert->setWidth(AphrontPanelView::WIDTH_FORM);

    $regen_form = new AphrontFormView();
    $regen_form
      ->setUser($user)
      ->setWorkflow(true)
      ->setAction('/settings/page/arcanist/')
      ->appendChild(
        '<p class="aphront-form-instructions">You can regenerate this '.
        'certificate, which will invalidate the old certificate and create '.
        'a new one.</p>')
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Regenerate Certificate'));

    $regen = new AphrontPanelView();
    $regen->setHeader('Regenerate Certificate');
    $regen->appendChild($regen_form);
    $regen->setWidth(AphrontPanelView::WIDTH_FORM);

    return $notice.$cert->render().$regen->render();
  }

  private function renderAccountForm(array $errors, $e_realname) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $img_src = PhabricatorFileURI::getViewURIForPHID(
      $user->getProfileImagePHID());

    $editable = $this->accountEditable;

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Changed Saved');
        $notice->appendChild('<p>Your changes have been saved.</p>');
        $notice = $notice->render();
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle('Form Errors');
      $notice->setErrors($errors);
      $notice = $notice->render();
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Username')
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Real Name')
          ->setName('realname')
          ->setError($e_realname)
          ->setValue($user->getRealName())
          ->setDisabled(!$editable))
      ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setValue('<hr />'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Profile Image')
          ->setValue(
            phutil_render_tag(
              'img',
              array(
                'src' => $img_src,
              ))));

    if ($editable) {
      $form
        ->appendChild(
          id(new AphrontFormFileControl())
            ->setLabel('Change Image')
            ->setName('profile')
            ->setCaption('Upload a 50x50px image.'))
        ->appendChild(
            id(new AphrontFormMarkupControl())
              ->setValue('<hr />'))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Save'));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Profile Settings');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return $notice.$panel->render();
  }

  private function renderEmailForm(array $errors, $e_email) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $editable = $this->accountEditable;

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Changed Saved');
        $notice->appendChild('<p>Your changes have been saved.</p>');
        $notice = $notice->render();
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle('Form Errors');
      $notice->setErrors($errors);
      $notice = $notice->render();
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Email')
          ->setName('email')
          ->setDisabled(!$editable)
          ->setCaption(
            'Note: there is no email validation yet; double-check your '.
            'typing.')
          ->setValue($user->getEmail())
          ->setError($e_email));

    if ($editable) {
      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Save'));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Email Settings');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return $notice.$panel->render();
  }

  private function renderOAuthForm(PhabricatorOAuthProvider $provider) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $notice = null;

    $provider_name = $provider->getProviderName();
    $provider_key = $provider->getProviderKey();

    $oauth_info = id(new PhabricatorUserOAuthInfo())->loadOneWhere(
      'userID = %d AND oauthProvider = %s',
      $user->getID(),
      $provider->getProviderKey());

    $form = new AphrontFormView();
    $form
      ->setUser($user);

    $forms = array();
    $forms[] = $form;
    if (!$oauth_info) {
      $form
        ->appendChild(
          '<p class="aphront-form-instructions">There is currently no '.
          $provider_name.' account linked to your Phabricator account. You '.
          'can link an account, which will allow you to use it to log into '.
          'Phabricator.</p>');

      switch ($provider_key) {
        case PhabricatorOAuthProvider::PROVIDER_GITHUB:
          $form->appendChild(
            '<p class="aphront-form-instructions">Additionally, you must '.
            'link your Github account before Phabricator can access any '.
            'information about hosted repositories.</p>');
          break;
      }

      $auth_uri = $provider->getAuthURI();
      $client_id = $provider->getClientID();
      $redirect_uri = $provider->getRedirectURI();

      $form
        ->setAction($auth_uri)
        ->setMethod('GET')
        ->addHiddenInput('redirect_uri', $redirect_uri)
        ->addHiddenInput('client_id', $client_id)
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Link '.$provider_name." Account \xC2\xBB"));
    } else {
      $form
        ->appendChild(
          '<p class="aphront-form-instructions">Your account is linked with '.
          'a '.$provider_name.' account. You may use your '.$provider_name.' '.
          'credentials to log into Phabricator.</p>')
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel($provider_name.' ID')
            ->setValue($oauth_info->getOAuthUID()))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel($provider_name.' Name')
            ->setValue($oauth_info->getAccountName()))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel($provider_name.' URI')
            ->setValue($oauth_info->getAccountURI()));

      if (!$provider->isProviderLinkPermanent()) {
        $unlink = 'Unlink '.$provider_name.' Account';
        $unlink_form = new AphrontFormView();
        $unlink_form
          ->setUser($user)
          ->appendChild(
            '<p class="aphront-form-instructions">You may unlink this account '.
            'from your '.$provider_name.' account. This will prevent you from '.
            'logging in with your '.$provider_name.' credentials.</p>')
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->addCancelButton('/oauth/'.$provider_key.'/unlink/', $unlink));
        $forms['Unlink Account'] = $unlink_form;
      }

      $expires = $oauth_info->getTokenExpires();
      if ($expires) {
        if ($expires <= time()) {
          $expires = "Expired";
        } else {
          $expires = phabricator_format_timestamp($expires);
        }
      } else {
        $expires = 'No Information Available';
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
            ->setValue($expires))
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel('Scope')
            ->setValue($scope));

      $forms['Account Token Information'] = $token_form;
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($provider_name.' Account Settings');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    foreach ($forms as $name => $form) {
      if ($name) {
        $panel->appendChild('<br /><br /><h1>'.$name.'</h1>');
      }
      $panel->appendChild($form);
    }

    return $notice.$panel->render();


  }

}
