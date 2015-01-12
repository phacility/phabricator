<?php

final class PhortuneLandingController extends PhortuneController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withMemberPHIDs(array($user->getPHID()))
      ->execute();

    if (!$accounts) {
      $account = PhortuneAccount::createNewAccount(
        $user,
        PhabricatorContentSource::newFromRequest($request));
      $accounts = array($account);
    }

    if (count($accounts) == 1) {
      $account = head($accounts);
      $next_uri = $this->getApplicationURI($account->getID().'/');
    } else {
      $next_uri = $this->getApplicationURI('account/');
    }

    return id(new AphrontRedirectResponse())->setURI($next_uri);
  }

}
