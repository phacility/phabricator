<?php

abstract class PhabricatorAuthProviderOAuth extends PhabricatorAuthProvider {

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $request = $controller->getRequest();
    $adapter = $this->getAdapter();
    $account = null;
    $response = null;

    $error = $request->getStr('error');
    if ($error) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider returned an error: %s',
          $error));

      return array($account, $response);
    }

    $code = $request->getStr('code');
    if (!strlen($code)) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider did not return a "code" parameter in its '.
          'response.'));

      return array($account, $response);
    }

    $adapter->setCode($code);

    // NOTE: As a side effect, this will cause the OAuth adapter to request
    // an access token.

    try {
      $account_id = $adapter->getAccountID();
    } catch (Exception $ex) {
      // TODO: Handle this in a more user-friendly way.
      throw $ex;
    }

    if (!strlen($account_id)) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider failed to retrieve an account ID.'));

      return array($account, $response);
    }

    return array($this->loadOrCreateAccount($account_id), $response);
  }

}
