<?php

final class PhortuneLandingController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $accounts = PhortuneAccountQuery::loadAccountsForUser(
      $viewer,
      PhabricatorContentSource::newFromRequest($request));

    if (count($accounts) == 1) {
      $account = head($accounts);
      $next_uri = $account->getURI();
    } else {
      $next_uri = $this->getApplicationURI('account/');
    }

    return id(new AphrontRedirectResponse())->setURI($next_uri);
  }

}
