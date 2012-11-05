<?php

final class PhabricatorLDAPUnlinkController extends PhabricatorAuthController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $ldap_info = id(new PhabricatorUserLDAPInfo())->loadOneWhere(
      'userID = %d',
      $user->getID());

    if (!$ldap_info) {
      return new Aphront400Response();
    }

    if (!$request->isDialogFormPost()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);
      $dialog->setTitle('Really unlink account?');
      $dialog->appendChild(
        '<p><strong>You will not be able to login</strong> using this account '.
        'once you unlink it. Continue?</p>');
      $dialog->addSubmitButton('Unlink Account');
      $dialog->addCancelButton('/settings/panel/ldap/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $ldap_info->delete();

    return id(new AphrontRedirectResponse())
      ->setURI('/settings/panel/ldap/');
  }

}
