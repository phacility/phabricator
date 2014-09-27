<?php

final class PhabricatorDisabledUserController
  extends PhabricatorAuthController {

  public function shouldRequireEnabledUser() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    if (!$user->getIsDisabled()) {
      return new Aphront404Response();
    }

    return id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Account Disabled'))
      ->addCancelButton('/logout/', pht('Okay'))
      ->appendParagraph(pht('Your account has been disabled.'));
  }

}
