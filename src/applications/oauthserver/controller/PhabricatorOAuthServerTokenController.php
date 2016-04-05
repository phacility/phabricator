<?php

final class PhabricatorOAuthServerTokenController
  extends PhabricatorOAuthServerController {

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldAllowRestrictedParameter($parameter_name) {
    if ($parameter_name == 'code') {
      return true;
    }
    return parent::shouldAllowRestrictedParameter($parameter_name);
  }

  public function handleRequest(AphrontRequest $request) {
    $grant_type = $request->getStr('grant_type');
    $code = $request->getStr('code');
    $redirect_uri = $request->getStr('redirect_uri');
    $client_phid = $request->getStr('client_id');
    $client_secret = $request->getStr('client_secret');
    $response = new PhabricatorOAuthResponse();
    $server = new PhabricatorOAuthServer();

    if ($grant_type != 'authorization_code') {
      $response->setError('unsupported_grant_type');
      $response->setErrorDescription(
        pht(
          'Only %s %s is supported.',
          'grant_type',
          'authorization_code'));
      return $response;
    }

    if (!$code) {
      $response->setError('invalid_request');
      $response->setErrorDescription(pht('Required parameter code missing.'));
      return $response;
    }

    if (!$client_phid) {
      $response->setError('invalid_request');
      $response->setErrorDescription(
        pht(
          'Required parameter %s missing.',
          'client_id'));
      return $response;
    }

    if (!$client_secret) {
      $response->setError('invalid_request');
      $response->setErrorDescription(
        pht(
          'Required parameter %s missing.',
          'client_secret'));
      return $response;
    }

    // one giant try / catch around all the exciting database stuff so we
    // can return a 'server_error' response if something goes wrong!
    try {
      $auth_code = id(new PhabricatorOAuthServerAuthorizationCode())
        ->loadOneWhere('code = %s',
                       $code);
      if (!$auth_code) {
        $response->setError('invalid_grant');
        $response->setErrorDescription(
          pht(
            'Authorization code %s not found.',
            $code));
        return $response;
      }

      // if we have an auth code redirect URI, there must be a redirect_uri
      // in the request and it must match the auth code redirect uri *exactly*
      $auth_code_redirect_uri = $auth_code->getRedirectURI();
      if ($auth_code_redirect_uri) {
        $auth_code_redirect_uri = new PhutilURI($auth_code_redirect_uri);
        $redirect_uri           = new PhutilURI($redirect_uri);
        if (!$redirect_uri->getDomain() ||
             $redirect_uri != $auth_code_redirect_uri) {
          $response->setError('invalid_grant');
          $response->setErrorDescription(
            pht(
              'Redirect URI in request must exactly match redirect URI '.
              'from authorization code.'));
          return $response;
        }
      } else if ($redirect_uri) {
        $response->setError('invalid_grant');
        $response->setErrorDescription(
          pht(
            'Redirect URI in request and no redirect URI in authorization '.
            'code. The two must exactly match.'));
        return $response;
      }

      $client = id(new PhabricatorOAuthServerClient())
        ->loadOneWhere('phid = %s', $client_phid);
      if (!$client) {
        $response->setError('invalid_client');
        $response->setErrorDescription(
          pht(
            'Client with %s %s not found.',
            'client_id',
            $client_phid));
        return $response;
      }

      if ($client->getIsDisabled()) {
        $response->setError('invalid_client');
        $response->setErrorDescription(
          pht(
            'OAuth application "%s" has been disabled.',
            $client->getName()));

        return $response;
      }

      $server->setClient($client);

      $user_phid = $auth_code->getUserPHID();
      $user = id(new PhabricatorUser())
        ->loadOneWhere('phid = %s', $user_phid);
      if (!$user) {
        $response->setError('invalid_grant');
        $response->setErrorDescription(
          pht(
            'User with PHID %s not found.',
            $user_phid));
        return $response;
      }
      $server->setUser($user);

      $test_code = new PhabricatorOAuthServerAuthorizationCode();
      $test_code->setClientSecret($client_secret);
      $test_code->setClientPHID($client_phid);
      $is_good_code = $server->validateAuthorizationCode(
        $auth_code,
        $test_code);
      if (!$is_good_code) {
        $response->setError('invalid_grant');
        $response->setErrorDescription(
          pht(
            'Invalid authorization code %s.',
            $code));
        return $response;
      }

      $unguarded    = AphrontWriteGuard::beginScopedUnguardedWrites();
      $access_token = $server->generateAccessToken();
      $auth_code->delete();
      unset($unguarded);
      $result = array(
        'access_token' => $access_token->getToken(),
        'token_type' => 'Bearer',
      );
      return $response->setContent($result);
    } catch (Exception $e) {
      $response->setError('server_error');
      $response->setErrorDescription(
        pht(
          'The authorization server encountered an unexpected condition '.
          'which prevented it from fulfilling the request.'));
      return $response;
    }
  }

}
