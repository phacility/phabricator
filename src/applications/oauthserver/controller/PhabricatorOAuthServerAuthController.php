<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthServerAuthController
extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return true;
  }

  public function processRequest() {
    $request       = $this->getRequest();
    $current_user  = $request->getUser();
    $server        = new PhabricatorOAuthServer();
    $client_phid   = $request->getStr('client_id');
    $scope         = $request->getStr('scope');
    $redirect_uri  = $request->getStr('redirect_uri');
    $state         = $request->getStr('state');
    $response_type = $request->getStr('response_type');
    $response      = new PhabricatorOAuthResponse();

    // state is an opaque value the client sent us for their own purposes
    // we just need to send it right back to them in the response!
    if ($state) {
      $response->setState($state);
    }
    if (!$client_phid) {
      $response->setError('invalid_request');
      $response->setErrorDescription(
        'Required parameter client_id not specified.'
      );
      return $response;
    }
    $server->setUser($current_user);

    // one giant try / catch around all the exciting database stuff so we
    // can return a 'server_error' response if something goes wrong!
    try {
      $client = id(new PhabricatorOAuthServerClient())
        ->loadOneWhere('phid = %s', $client_phid);
      if (!$client) {
        $response->setError('invalid_request');
        $response->setErrorDescription(
          'Client with id '.$client_phid.' not found.'
        );
        return $response;
      }
      $server->setClient($client);
      if ($redirect_uri) {
        $client_uri   = new PhutilURI($client->getRedirectURI());
        $redirect_uri = new PhutilURI($redirect_uri);
        if (!($server->validateSecondaryRedirectURI($redirect_uri,
                                                    $client_uri))) {
          $response->setError('invalid_request');
          $response->setErrorDescription(
            'The specified redirect URI is invalid. The redirect URI '.
            'must be a fully-qualified domain with no fragments and '.
            'must have the same domain and at least the same query '.
            'parameters as the redirect URI the client registered.'
          );
          return $response;
        }
        $uri              = $redirect_uri;
        $access_token_uri = $uri;
      } else {
        $uri              = new PhutilURI($client->getRedirectURI());
        $access_token_uri = null;
      }
      // we've now validated this request enough overall such that we
      // can safely redirect to the client with the response
      $response->setClientURI($uri);

      if (empty($response_type)) {
        $response->setError('invalid_request');
        $response->setErrorDescription(
          'Required parameter response_type not specified.'
        );
        return $response;
      }
      if ($response_type != 'code') {
        $response->setError('unsupported_response_type');
        $response->setErrorDescription(
          'The authorization server does not support obtaining an '.
          'authorization code using the specified response_type. '.
          'You must specify the response_type as "code".'
        );
        return $response;
      }
      if ($scope) {
        if (!PhabricatorOAuthServerScope::validateScopesList($scope)) {
          $response->setError('invalid_scope');
          $response->setErrorDescription(
            'The requested scope is invalid, unknown, or malformed.'
          );
          return $response;
        }
        $scope = PhabricatorOAuthServerScope::scopesListToDict($scope);
      }

      list($is_authorized,
           $authorization) = $server->userHasAuthorizedClient($scope);
      if ($is_authorized) {
        $return_auth_code = true;
        $unguarded_write  = AphrontWriteGuard::beginScopedUnguardedWrites();
      } else if ($request->isFormPost()) {
        $scope = PhabricatorOAuthServerScope::getScopesFromRequest($request);
        if ($authorization) {
          $authorization->setScope($scope)->save();
        } else {
          $authorization  = $server->authorizeClient($scope);
        }
        $return_auth_code = true;
        $unguarded_write  = null;
      } else {
        $return_auth_code = false;
        $unguarded_write  = null;
      }

      if ($return_auth_code) {
        // step 1 -- generate authorization code
        $auth_code =
          $server->generateAuthorizationCode($access_token_uri);

        // step 2 return it
        $content = array(
          'code'  => $auth_code->getCode(),
          'scope' => $authorization->getScopeString(),
        );
        $response->setContent($content);
        return $response;
      }
      unset($unguarded_write);
    } catch (Exception $e) {
      // Note we could try harder to determine between a server_error
      // vs temporarily_unavailable.  Good enough though.
      $response->setError('server_error');
      $response->setErrorDescription(
        'The authorization server encountered an unexpected condition '.
        'which prevented it from fulfilling the request. '
      );
      return $response;
    }

    // display time -- make a nice form for the user to grant the client
    // access to the granularity specified by $scope
    $name  = phutil_escape_html($client->getName());
    $title = 'Authorize ' . $name . '?';
    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader($title);

    $description =
      "Do want to authorize {$name} to access your ".
      "Phabricator account data?";

    if ($scope) {
      if ($authorization) {
        $desired_scopes = array_merge($scope,
                                      $authorization->getScope());
      } else {
        $desired_scopes = $scope;
      }
      if (!PhabricatorOAuthServerScope::validateScopesDict($desired_scopes)) {
        $response->setError('invalid_scope');
        $response->setErrorDescription(
          'The requested scope is invalid, unknown, or malformed.'
        );
        return $response;
      }
    } else {
      $desired_scopes = array(
        PhabricatorOAuthServerScope::SCOPE_WHOAMI         => 1,
        PhabricatorOAuthServerScope::SCOPE_OFFLINE_ACCESS => 1
      );
    }

    $cancel_uri = clone $uri;
    $cancel_params = array(
      'error' => 'access_denied',
      'error_description' =>
        'The resource owner (aka the user) denied the request.'
    );
    $cancel_uri->setQueryParams($cancel_params);

    $form = id(new AphrontFormView())
      ->setUser($current_user)
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setValue($description)
      )
      ->appendChild(
        PhabricatorOAuthServerScope::getCheckboxControl($desired_scopes)
      )
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Authorize')
        ->addCancelButton($cancel_uri)
      );

    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
            $panel,
      array('title' => $title));
  }

}
