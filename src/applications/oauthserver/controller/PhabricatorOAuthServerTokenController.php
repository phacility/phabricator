<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthServerTokenController
extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request       = $this->getRequest();
    $grant_type    = $request->getStr('grant_type');
    $code          = $request->getStr('code');
    $redirect_uri  = $request->getStr('redirect_uri');
    $client_phid   = $request->getStr('client_id');
    $client_secret = $request->getStr('client_secret');
    $response      = new PhabricatorOAuthResponse();
    $server        = new PhabricatorOAuthServer();
    if ($grant_type != 'authorization_code') {
      $response->setError('unsupported_grant_type');
      $response->setErrorDescription(
        'Only grant_type authorization_code is supported.'
      );
      return $response;
    }
    if (!$code) {
      $response->setError('invalid_request');
      $response->setErrorDescription(
        'Required parameter code missing.'
      );
      return $response;
    }
    if (!$client_phid) {
      $response->setError('invalid_request');
      $response->setErrorDescription(
        'Required parameter client_id missing.'
      );
      return $response;
    }
    if (!$client_secret) {
      $response->setError('invalid_request');
      $response->setErrorDescription(
        'Required parameter client_secret missing.'
      );
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
          'Authorization code '.$code.' not found.'
        );
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
            'Redirect uri in request must exactly match redirect uri '.
            'from authorization code.'
          );
          return $response;
        }
      } else if ($redirect_uri) {
        $response->setError('invalid_grant');
        $response->setErrorDescription(
          'Redirect uri in request and no redirect uri in authorization '.
          'code. The two must exactly match.'
        );
        return $response;
      }

      $client = id(new PhabricatorOAuthServerClient())
        ->loadOneWhere('phid = %s',
                       $client_phid);
      if (!$client) {
        $response->setError('invalid_client');
        $response->setErrorDescription(
          'Client with client_id '.$client_phid.' not found.'
        );
        return $response;
      }
      $server->setClient($client);

      $user_phid = $auth_code->getUserPHID();
      $user = id(new PhabricatorUser())
        ->loadOneWhere('phid = %s', $user_phid);
      if (!$user) {
        $response->setError('invalid_grant');
        $response->setErrorDescription(
          'User with phid '.$user_phid.' not found.'
        );
        return $response;
      }
      $server->setUser($user);

      $test_code = new PhabricatorOAuthServerAuthorizationCode();
      $test_code->setClientSecret($client_secret);
      $test_code->setClientPHID($client_phid);
      $is_good_code = $server->validateAuthorizationCode($auth_code,
                                                         $test_code);
      if (!$is_good_code) {
        $response->setError('invalid_grant');
        $response->setErrorDescription(
          'Invalid authorization code '.$code.'.'
        );
        return $response;
      }

      $unguarded    = AphrontWriteGuard::beginScopedUnguardedWrites();
      $access_token = $server->generateAccessToken();
      $auth_code->delete();
      unset($unguarded);
      $result = array(
        'access_token' => $access_token->getToken(),
        'token_type'   => 'Bearer',
        'expires_in'   => PhabricatorOAuthServer::ACCESS_TOKEN_TIMEOUT,
      );
      return $response->setContent($result);
    } catch (Exception $e) {
      $response->setError('server_error');
      $response->setErrorDescription(
        'The authorization server encountered an unexpected condition '.
        'which prevented it from fulfilling the request.'
      );
      return $response;
    }
  }
}
