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
      $unlink = pht('Link LDAP Account');
      $unlink_form = new AphrontFormView();
      $unlink_form
        ->setUser($user)
        ->setAction('/ldap/login/')
        ->appendChild(hsprintf(
          '<p class="aphront-form-instructions">%s</p>',
          pht('There is currently no LDAP account linked to your Phabricator '.
          'account. You can link an account, which will allow you to use it '.
          'to log into Phabricator.')))
        ->appendChild(
          id(new AphrontFormTextControl())
          ->setLabel(pht('LDAP username'))
          ->setName('username'))
        ->appendChild(
          id(new AphrontFormPasswordControl())
          ->setLabel(pht('Password'))
          ->setName('password'))
          ->appendChild(
            id(new AphrontFormSubmitControl())
            ->setValue(pht("Link LDAP Account \xC2\xBB")));

      $forms['Link Account'] = $unlink_form;
    } else {
      $unlink = pht('Unlink LDAP Account');
      $unlink_form = new AphrontFormView();
      $unlink_form
        ->setUser($user)
        ->appendChild(hsprintf(
          '<p class="aphront-form-instructions">%s</p>',
          pht('You may unlink this account from your LDAP account. This will '.
          'prevent you from logging in with your LDAP credentials.')))
        ->appendChild(
          id(new AphrontFormSubmitControl())
          ->addCancelButton('/ldap/unlink/', $unlink));

      $forms['Unlink Account'] = $unlink_form;
    }

    $header = new PhabricatorHeaderView();
    $header->setHeader(pht('LDAP Account Settings'));

    $formbox = new PHUIBoxView();
    foreach ($forms as $name => $form) {
      if ($name) {
        $head = new PhabricatorHeaderView();
        $head->setHeader($name);
        $formbox->appendChild($head);
      }
      $formbox->appendChild($form);
    }

    return array(
      $header,
      $formbox,
    );
  }
}
