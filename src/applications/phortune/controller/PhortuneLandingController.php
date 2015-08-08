<?php

final class PhortuneLandingController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withMemberPHIDs(array($viewer->getPHID()))
      ->execute();

    if (!$accounts) {
      $account = PhortuneAccount::createNewAccount(
        $viewer,
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
