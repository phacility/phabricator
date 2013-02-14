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
      $dialog->setTitle(pht('Really unlink account?'));
      $dialog->appendChild(phutil_tag('p', array(), pht(
        'You will not be able to login using this account '.
        'once you unlink it. Continue?')));
      $dialog->addSubmitButton(pht('Unlink Account'));
      $dialog->addCancelButton('/settings/panel/ldap/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $ldap_info->delete();

    return id(new AphrontRedirectResponse())
      ->setURI('/settings/panel/ldap/');
  }

}
