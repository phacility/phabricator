<?php

final class PhabricatorSettingsPanelLDAP
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'ldap';
  }

  public function getPanelName() {
    return pht('LDAP');
  }

  public function getPanelGroup() {
    return pht('Linked Accounts');
  }

  public function isEnabled() {
    $ldap_provider = new PhabricatorLDAPProvider();
    return $ldap_provider->isProviderEnabled();
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $ldap_info = id(new PhabricatorUserLDAPInfo())->loadOneWhere(
      'userID = %d',
      $user->getID());

    $forms = array();

    if (!$ldap_info) {
      $unlink = 'Link LDAP Account';
      $unlink_form = new AphrontFormView();
      $unlink_form
        ->setUser($user)
        ->setAction('/ldap/login/')
        ->appendChild(
          '<p class="aphront-form-instructions">There is currently no '.
          'LDAP account linked to your Phabricator account. You can link an ' .
          'account, which will allow you to use it to log into Phabricator</p>')
        ->appendChild(
          id(new AphrontFormTextControl())
          ->setLabel('LDAP username')
          ->setName('username'))
        ->appendChild(
          id(new AphrontFormPasswordControl())
          ->setLabel('Password')
          ->setName('password'))
          ->appendChild(
            id(new AphrontFormSubmitControl())
            ->setValue("Link LDAP Account \xC2\xBB"));

      $forms['Link Account'] = $unlink_form;
    } else {
      $unlink = 'Unlink LDAP Account';
      $unlink_form = new AphrontFormView();
      $unlink_form
        ->setUser($user)
        ->appendChild(
          '<p class="aphront-form-instructions">You may unlink this account '.
          'from your LDAP account. This will prevent you from logging in with '.
          'your LDAP credentials.</p>')
        ->appendChild(
          id(new AphrontFormSubmitControl())
          ->addCancelButton('/ldap/unlink/', $unlink));

      $forms['Unlink Account'] = $unlink_form;
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('LDAP Account Settings');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    foreach ($forms as $name => $form) {
      if ($name) {
        $panel->appendChild('<br /><h1>'.$name.'</h1><br />');
      }
      $panel->appendChild($form);
    }

    return array(
      $panel,
    );
  }
}
