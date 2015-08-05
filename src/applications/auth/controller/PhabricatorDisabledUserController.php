<?php

final class PhabricatorDisabledUserController
  extends PhabricatorAuthController {

  public function shouldRequireEnabledUser() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    if (!$viewer->getIsDisabled()) {
      return new Aphront404Response();
    }

    return id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Account Disabled'))
      ->addCancelButton('/logout/', pht('Okay'))
      ->appendParagraph(pht('Your account has been disabled.'));
  }

}
