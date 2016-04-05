<?php

final class PhabricatorAuthLogoutConduitAPIMethod
  extends PhabricatorAuthConduitAPIMethod {

  public function getAPIMethodName() {
    return 'auth.logout';
  }

  public function getMethodSummary() {
    return pht('Terminate all login sessions.');
  }

  public function getMethodDescription() {
    return pht(
      'Terminate all web login sessions. If called via OAuth, also terminate '.
      'the current OAuth token.'.
      "\n\n".
      'WARNING: This method does what it claims on the label. If you call '.
      'this method via the test console in the web UI, it will log you out!');
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'void';
  }

  public function getRequiredScope() {
    return self::SCOPE_ALWAYS;
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    // Destroy all web sessions.
    $engine = id(new PhabricatorAuthSessionEngine());
    $engine->terminateLoginSessions($viewer);

    // If we were called via OAuth, destroy the OAuth token.
    $oauth_token = $request->getOAuthToken();
    if ($oauth_token) {
      $oauth_token->delete();
    }

    return null;
  }

}
