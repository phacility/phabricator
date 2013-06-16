<?php

final class PhabricatorLDAPUnlinkController extends PhabricatorAuthController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $ldap_account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'userPHID = %s AND accountType = %s AND accountDomain = %s',
      $user->getPHID(),
      'ldap',
      'self');

    if (!$ldap_account) {
      return new Aphront400Response();
    }

    if (!$request->isDialogFormPost()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);
      $dialog->setTitle(pht('Really unlink account?'));
      $dialog->appendChild(phutil_tag('p', array(), pht(
        'You will not be able to login using this account '.
        'once you unlink it. Continue?')));
      $dialog->addSubmitButton(pht('Unlink Account'));
      $dialog->addCancelButton('/settings/panel/ldap/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $ldap_account->delete();

    return id(new AphrontRedirectResponse())
      ->setURI('/settings/panel/ldap/');
  }

}
