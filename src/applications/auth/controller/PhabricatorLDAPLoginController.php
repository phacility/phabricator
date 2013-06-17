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
            $existing_ldap = id(new PhabricatorExternalAccount())->loadOneWhere(
              'accountType = %s AND accountDomain = %s AND userPHID = %s',
              'ldap',
              'self',
              $current_user->getPHID());

            if ($ldap_info->getUserPHID() != $current_user->getPHID() ||
                $existing_ldap) {
              $dialog = new AphrontDialogView();
              $dialog->setUser($current_user);
              $dialog->setTitle(pht('Already Linked to Another Account'));
              $dialog->appendChild(phutil_tag('p', array(), pht(
                'The LDAP account you just authorized is already '.
                'linked toanother Phabricator account. Before you can link it '.
                'to a different LDAP account, you must unlink the old '.
                'account.')));
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
            $dialog->setTitle(pht('Link LDAP Account'));
            $dialog->appendChild(phutil_tag('p', array(), pht(
              'Link your LDAP account to your Phabricator account?')));
            $dialog->addHiddenInput('username', $request->getStr('username'));
            $dialog->addHiddenInput('password', $request->getStr('password'));
            $dialog->addSubmitButton(pht('Link Accounts'));
            $dialog->addCancelButton('/settings/panel/ldap/');

            return id(new AphrontDialogResponse())->setDialog($dialog);
          }

          $ldap_info->setUserPHID($current_user->getPHID());

          $this->saveLDAPInfo($ldap_info);

          return id(new AphrontRedirectResponse())
            ->setURI('/settings/panel/ldap/');
        }

        if ($ldap_info->getUserPHID()) {
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

          $known_user = id(new PhabricatorUser())->loadOneWhere(
            'phid = %s',
            $ldap_info->getUserPHID());

          $this->saveLDAPInfo($ldap_info);

          return $this->loginUser($known_user);
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
        ->setLabel(pht('LDAP username'))
        ->setName('username')
        ->setValue($ldap_username))
      ->appendChild(
        id(new AphrontFormPasswordControl())
        ->setLabel(pht('Password'))
        ->setName('password'));

    $ldap_form
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue(pht('Login')));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild(phutil_tag('h1', array(), pht('LDAP login')));
    $panel->appendChild($ldap_form);

    $error_view = null;
    if (isset($errors) && count($errors) > 0) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Login Failed'));
      $error_view->setErrors($errors);
    }

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => pht('Login'),
      ));
  }

  private function retrieveLDAPInfo(PhabricatorLDAPProvider $provider) {
    $ldap_info = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountType = %s AND accountDomain = %s AND accountID = %s',
      'ldap',
      'self',
      $provider->retrieveUsername());

    if (!$ldap_info) {
      $ldap_info = id(new PhabricatorExternalAccount())
        ->setAccountType('ldap')
        ->setAccountDomain('self')
        ->setAccountID($provider->retrieveUsername());
    }

    return $ldap_info;
  }

  private function saveLDAPInfo(PhabricatorExternalAccount $info) {
    // UNGUARDED WRITES: Logging-in users don't have their CSRF set up yet.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $info->save();
  }
}
