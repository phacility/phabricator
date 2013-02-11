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

    $failure_view = new AphrontRequestFailureView();
    $failure_view->setHeader(pht('Account Disabled'));
    $failure_view->appendChild(phutil_tag('p', array(), pht(
      'Your account has been disabled.')));

    return $this->buildStandardPageResponse(
      $failure_view,
      array(
        'title' => pht('Account Disabled'),
      ));
  }

}
