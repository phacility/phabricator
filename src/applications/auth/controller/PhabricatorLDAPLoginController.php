<?php

final class PhabricatorLDAPLoginController extends PhabricatorAuthController {
  private $provider;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->provider = new PhabricatorLDAPProvider();
  }

  public function processRequest() {
    if (!$this->provider->isProviderEnabled()) {
      return new Aphront400Response();
    }

    $current_user = $this->getRequest()->getUser();
    $request = $this->getRequest();

    $ldap_username = $request->getCookie('phusr');
    if ($request->isFormPost()) {
      $ldap_username = $request->getStr('username');
      try {
        $envelope = new PhutilOpaqueEnvelope($request->getStr('password'));
        $this->provider->auth($ldap_username, $envelope);
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }

      if (empty($errors)) {
        $ldap_info = $this->retrieveLDAPInfo($this->provider);

        if ($current_user->getPHID()) {
          if ($ldap_info->getID()) {
            $existing_ldap = id(new PhabricatorUserLDAPInfo())->loadOneWhere(
              'userID = %d',
              $current_user->getID());

            if ($ldap_info->getUserID() != $current_user->getID() ||
                $existing_ldap) {
              $dialog = new AphrontDialogView();
              $dialog->setUser($current_user);
              $dialog->setTitle('Already Linked to Another Account');
              $dialog->appendChild(
                '<p>The LDAP account you just authorized is already linked to '.
                'another Phabricator account. Before you can link it to a '.
                'different LDAP account, you must unlink the old account.</p>'
              );
              $dialog->addCancelButton('/settings/panel/ldap/');

              return id(new AphrontDialogResponse())->setDialog($dialog);
            } else {
              return id(new AphrontRedirectResponse())
                ->setURI('/settings/panel/ldap/');
            }
          }

          if (!$request->isDialogFormPost()) {
            $dialog = new AphrontDialogView();
            $dialog->setUser($current_user);
            $dialog->setTitle('Link LDAP Account');
            $dialog->appendChild(
              '<p>Link your LDAP account to your Phabricator account?</p>');
            $dialog->addHiddenInput('username', $request->getStr('username'));
            $dialog->addHiddenInput('password', $request->getStr('password'));
            $dialog->addSubmitButton('Link Accounts');
            $dialog->addCancelButton('/settings/panel/ldap/');

            return id(new AphrontDialogResponse())->setDialog($dialog);
          }

          $ldap_info->setUserID($current_user->getID());

          $this->saveLDAPInfo($ldap_info);

          return id(new AphrontRedirectResponse())
            ->setURI('/settings/panel/ldap/');
        }

        if ($ldap_info->getID()) {
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

          $known_user = id(new PhabricatorUser())->load(
              $ldap_info->getUserID());

          $session_key = $known_user->establishSession('web');

          $this->saveLDAPInfo($ldap_info);

          $request->setCookie('phusr', $known_user->getUsername());
          $request->setCookie('phsid', $session_key);

          $uri = new PhutilURI('/login/validate/');
          $uri->setQueryParams(
            array(
              'phusr' => $known_user->getUsername(),
            ));

          return id(new AphrontRedirectResponse())->setURI((string)$uri);
        }

        $controller = newv('PhabricatorLDAPRegistrationController',
                      array($this->getRequest()));
        $controller->setLDAPProvider($this->provider);
        $controller->setLDAPInfo($ldap_info);

        return $this->delegateToController($controller);
      }
    }

    $ldap_form = new AphrontFormView();
    $ldap_form
      ->setUser($request->getUser())
      ->setAction('/ldap/login/')
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('LDAP username')
        ->setName('username')
        ->setValue($ldap_username))
      ->appendChild(
        id(new AphrontFormPasswordControl())
        ->setLabel('Password')
        ->setName('password'));

    $ldap_form
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Login'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild('<h1>LDAP login</h1>');
    $panel->appendChild($ldap_form);

    if (isset($errors) && count($errors) > 0) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Login Failed');
      $error_view->setErrors($errors);
    }

    return $this->buildStandardPageResponse(
      array(
        isset($error_view) ? $error_view : null,
        $panel,
      ),
      array(
        'title' => 'Login',
      ));
  }

  private function retrieveLDAPInfo(PhabricatorLDAPProvider $provider) {
    $ldap_info = id(new PhabricatorUserLDAPInfo())->loadOneWhere(
      'ldapUsername = %s',
      $provider->retrieveUsername());

    if (!$ldap_info) {
      $ldap_info = new PhabricatorUserLDAPInfo();
      $ldap_info->setLDAPUsername($provider->retrieveUsername());
    }

    return $ldap_info;
  }

  private function saveLDAPInfo(PhabricatorUserLDAPInfo $info) {
    // UNGUARDED WRITES: Logging-in users don't have their CSRF set up yet.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $info->save();
  }
}
