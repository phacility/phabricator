<?php

final class PhabricatorConduitAPIController
  extends PhabricatorConduitController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $method = $request->getURIData('method');
    $time_start = microtime(true);

    $api_request = null;
    $method_implementation = null;

    $log = new PhabricatorConduitMethodCallLog();
    $log->setMethod($method);
    $metadata = array();

    $multimeter = MultimeterControl::getInstance();
    if ($multimeter) {
      $multimeter->setEventContext('api.'.$method);
    }

    try {

      list($metadata, $params) = $this->decodeConduitParams($request, $method);

      $call = new ConduitCall($method, $params);
      $method_implementation = $call->getMethodImplementation();

      $result = null;

      // TODO: The relationship between ConduitAPIRequest and ConduitCall is a
      // little odd here and could probably be improved. Specifically, the
      // APIRequest is a sub-object of the Call, which does not parallel the
      // role of AphrontRequest (which is an indepenent object).
      // In particular, the setUser() and getUser() existing independently on
      // the Call and APIRequest is very awkward.

      $api_request = $call->getAPIRequest();

      $allow_unguarded_writes = false;
      $auth_error = null;
      $conduit_username = '-';
      if ($call->shouldRequireAuthentication()) {
        $metadata['scope'] = $call->getRequiredScope();
        $auth_error = $this->authenticateUser($api_request, $metadata, $method);
        // If we've explicitly authenticated the user here and either done
        // CSRF validation or are using a non-web authentication mechanism.
        $allow_unguarded_writes = true;

        if ($auth_error === null) {
          $conduit_user = $api_request->getUser();
          if ($conduit_user && $conduit_user->getPHID()) {
            $conduit_username = $conduit_user->getUsername();
          }
          $call->setUser($api_request->getUser());
        }
      }

      $access_log = PhabricatorAccessLog::getLog();
      if ($access_log) {
        $access_log->setData(
          array(
            'u' => $conduit_username,
            'm' => $method,
          ));
      }

      if ($call->shouldAllowUnguardedWrites()) {
        $allow_unguarded_writes = true;
      }

      if ($auth_error === null) {
        if ($allow_unguarded_writes) {
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        }

        try {
          $result = $call->execute();
          $error_code = null;
          $error_info = null;
        } catch (ConduitException $ex) {
          $result = null;
          $error_code = $ex->getMessage();
          if ($ex->getErrorDescription()) {
            $error_info = $ex->getErrorDescription();
          } else {
            $error_info = $call->getErrorDescription($error_code);
          }
        }
        if ($allow_unguarded_writes) {
          unset($unguarded);
        }
      } else {
        list($error_code, $error_info) = $auth_error;
      }
    } catch (Exception $ex) {
      if (!($ex instanceof ConduitMethodNotFoundException)) {
        phlog($ex);
      }
      $result = null;
      $error_code = ($ex instanceof ConduitException
        ? 'ERR-CONDUIT-CALL'
        : 'ERR-CONDUIT-CORE');
      $error_info = $ex->getMessage();
    }

    $time_end = microtime(true);

    $connection_id = null;
    if (idx($metadata, 'connectionID')) {
      $connection_id = $metadata['connectionID'];
    } else if (($method == 'conduit.connect') && $result) {
      $connection_id = idx($result, 'connectionID');
    }

    $log
      ->setCallerPHID(
        isset($conduit_user)
          ? $conduit_user->getPHID()
          : null)
      ->setConnectionID($connection_id)
      ->setError((string)$error_code)
      ->setDuration(1000000 * ($time_end - $time_start));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $log->save();
    unset($unguarded);

    $response = id(new ConduitAPIResponse())
      ->setResult($result)
      ->setErrorCode($error_code)
      ->setErrorInfo($error_info);

    switch ($request->getStr('output')) {
      case 'human':
        return $this->buildHumanReadableResponse(
          $method,
          $api_request,
          $response->toDictionary(),
          $method_implementation);
      case 'json':
      default:
        return id(new AphrontJSONResponse())
          ->setAddJSONShield(false)
          ->setContent($response->toDictionary());
    }
  }

  /**
   * Authenticate the client making the request to a Phabricator user account.
   *
   * @param   ConduitAPIRequest Request being executed.
   * @param   dict              Request metadata.
   * @return  null|pair         Null to indicate successful authentication, or
   *                            an error code and error message pair.
   */
  private function authenticateUser(
    ConduitAPIRequest $api_request,
    array $metadata,
    $method) {

    $request = $this->getRequest();

    if ($request->getUser()->getPHID()) {
      $request->validateCSRF();
      return $this->validateAuthenticatedUser(
        $api_request,
        $request->getUser());
    }

    $auth_type = idx($metadata, 'auth.type');
    if ($auth_type === ConduitClient::AUTH_ASYMMETRIC) {
      $host = idx($metadata, 'auth.host');
      if (!$host) {
        return array(
          'ERR-INVALID-AUTH',
          pht(
            'Request is missing required "%s" parameter.',
            'auth.host'),
        );
      }

      // TODO: Validate that we are the host!

      $raw_key = idx($metadata, 'auth.key');
      $public_key = PhabricatorAuthSSHPublicKey::newFromRawKey($raw_key);
      $ssl_public_key = $public_key->toPKCS8();

      // First, verify the signature.
      try {
        $protocol_data = $metadata;

        // TODO: We should stop writing this into the protocol data when
        // processing a request.
        unset($protocol_data['scope']);

        ConduitClient::verifySignature(
          $method,
          $api_request->getAllParameters(),
          $protocol_data,
          $ssl_public_key);
      } catch (Exception $ex) {
        return array(
          'ERR-INVALID-AUTH',
          pht(
            'Signature verification failure. %s',
            $ex->getMessage()),
        );
      }

      // If the signature is valid, find the user or device which is
      // associated with this public key.

      $stored_key = id(new PhabricatorAuthSSHKeyQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withKeys(array($public_key))
        ->executeOne();
      if (!$stored_key) {
        return array(
          'ERR-INVALID-AUTH',
          pht('No user or device is associated with that public key.'),
        );
      }

      $object = $stored_key->getObject();

      if ($object instanceof PhabricatorUser) {
        $user = $object;
      } else {
        if (!$stored_key->getIsTrusted()) {
          return array(
            'ERR-INVALID-AUTH',
            pht(
              'The key which signed this request is not trusted. Only '.
              'trusted keys can be used to sign API calls.'),
          );
        }

        if (!PhabricatorEnv::isClusterRemoteAddress()) {
          return array(
            'ERR-INVALID-AUTH',
            pht(
              'This request originates from outside of the Phabricator '.
              'cluster address range. Requests signed with trusted '.
              'device keys must originate from within the cluster.'),
          );
        }

        $user = PhabricatorUser::getOmnipotentUser();

        // Flag this as an intracluster request.
        $api_request->setIsClusterRequest(true);
      }

      return $this->validateAuthenticatedUser(
        $api_request,
        $user);
    } else if ($auth_type === null) {
      // No specified authentication type, continue with other authentication
      // methods below.
    } else {
      return array(
        'ERR-INVALID-AUTH',
        pht(
          'Provided "%s" ("%s") is not recognized.',
          'auth.type',
          $auth_type),
      );
    }

    $token_string = idx($metadata, 'token');
    if (strlen($token_string)) {

      if (strlen($token_string) != 32) {
        return array(
          'ERR-INVALID-AUTH',
          pht(
            'API token "%s" has the wrong length. API tokens should be '.
            '32 characters long.',
            $token_string),
        );
      }

      $type = head(explode('-', $token_string));
      $valid_types = PhabricatorConduitToken::getAllTokenTypes();
      $valid_types = array_fuse($valid_types);
      if (empty($valid_types[$type])) {
        return array(
          'ERR-INVALID-AUTH',
          pht(
            'API token "%s" has the wrong format. API tokens should be '.
            '32 characters long and begin with one of these prefixes: %s.',
            $token_string,
            implode(', ', $valid_types)),
          );
      }

      $token = id(new PhabricatorConduitTokenQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withTokens(array($token_string))
        ->withExpired(false)
        ->executeOne();
      if (!$token) {
        $token = id(new PhabricatorConduitTokenQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withTokens(array($token_string))
          ->withExpired(true)
          ->executeOne();
        if ($token) {
          return array(
            'ERR-INVALID-AUTH',
            pht(
              'API token "%s" was previously valid, but has expired.',
              $token_string),
          );
        } else {
          return array(
            'ERR-INVALID-AUTH',
            pht(
              'API token "%s" is not valid.',
              $token_string),
          );
        }
      }

      // If this is a "cli-" token, it expires shortly after it is generated
      // by default. Once it is actually used, we extend its lifetime and make
      // it permanent. This allows stray tokens to get cleaned up automatically
      // if they aren't being used.
      if ($token->getTokenType() == PhabricatorConduitToken::TYPE_COMMANDLINE) {
        if ($token->getExpires()) {
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
            $token->setExpires(null);
            $token->save();
          unset($unguarded);
        }
      }

      // If this is a "clr-" token, Phabricator must be configured in cluster
      // mode and the remote address must be a cluster node.
      if ($token->getTokenType() == PhabricatorConduitToken::TYPE_CLUSTER) {
        if (!PhabricatorEnv::isClusterRemoteAddress()) {
          return array(
            'ERR-INVALID-AUTH',
            pht(
              'This request originates from outside of the Phabricator '.
              'cluster address range. Requests signed with cluster API '.
              'tokens must originate from within the cluster.'),
          );
        }

        // Flag this as an intracluster request.
        $api_request->setIsClusterRequest(true);
      }

      $user = $token->getObject();
      if (!($user instanceof PhabricatorUser)) {
        return array(
          'ERR-INVALID-AUTH',
          pht('API token is not associated with a valid user.'),
        );
      }

      return $this->validateAuthenticatedUser(
        $api_request,
        $user);
    }

    // handle oauth
    $access_token = idx($metadata, 'access_token');
    $method_scope = idx($metadata, 'scope');
    if ($access_token &&
        $method_scope != PhabricatorOAuthServerScope::SCOPE_NOT_ACCESSIBLE) {
      $token = id(new PhabricatorOAuthServerAccessToken())
        ->loadOneWhere('token = %s', $access_token);
      if (!$token) {
        return array(
          'ERR-INVALID-AUTH',
          pht('Access token does not exist.'),
        );
      }

      $oauth_server = new PhabricatorOAuthServer();
      $valid = $oauth_server->validateAccessToken($token,
                                                  $method_scope);
      if (!$valid) {
        return array(
          'ERR-INVALID-AUTH',
          pht('Access token is invalid.'),
        );
      }

      // valid token, so let's log in the user!
      $user_phid = $token->getUserPHID();
      $user = id(new PhabricatorUser())
        ->loadOneWhere('phid = %s', $user_phid);
      if (!$user) {
        return array(
          'ERR-INVALID-AUTH',
          pht('Access token is for invalid user.'),
        );
      }
      return $this->validateAuthenticatedUser(
        $api_request,
        $user);
    }

    // Handle sessionless auth.
    // TODO: This is super messy.
    // TODO: Remove this in favor of token-based auth.

    if (isset($metadata['authUser'])) {
      $user = id(new PhabricatorUser())->loadOneWhere(
        'userName = %s',
        $metadata['authUser']);
      if (!$user) {
        return array(
          'ERR-INVALID-AUTH',
          pht('Authentication is invalid.'),
        );
      }
      $token = idx($metadata, 'authToken');
      $signature = idx($metadata, 'authSignature');
      $certificate = $user->getConduitCertificate();
      $hash = sha1($token.$certificate);
      if (!phutil_hashes_are_identical($hash, $signature)) {
        return array(
          'ERR-INVALID-AUTH',
          pht('Authentication is invalid.'),
        );
      }
      return $this->validateAuthenticatedUser(
        $api_request,
        $user);
    }

    // Handle session-based auth.
    // TODO: Remove this in favor of token-based auth.

    $session_key = idx($metadata, 'sessionKey');
    if (!$session_key) {
      return array(
        'ERR-INVALID-SESSION',
        pht('Session key is not present.'),
      );
    }

    $user = id(new PhabricatorAuthSessionEngine())
      ->loadUserForSession(PhabricatorAuthSession::TYPE_CONDUIT, $session_key);

    if (!$user) {
      return array(
        'ERR-INVALID-SESSION',
        pht('Session key is invalid.'),
      );
    }

    return $this->validateAuthenticatedUser(
      $api_request,
      $user);
  }

  private function validateAuthenticatedUser(
    ConduitAPIRequest $request,
    PhabricatorUser $user) {

    if (!$user->canEstablishAPISessions()) {
      return array(
        'ERR-INVALID-AUTH',
        pht('User account is not permitted to use the API.'),
      );
    }

    $request->setUser($user);
    return null;
  }

  private function buildHumanReadableResponse(
    $method,
    ConduitAPIRequest $request = null,
    $result = null,
    ConduitAPIMethod $method_implementation = null) {

    $param_rows = array();
    $param_rows[] = array('Method', $this->renderAPIValue($method));
    if ($request) {
      foreach ($request->getAllParameters() as $key => $value) {
        $param_rows[] = array(
          $key,
          $this->renderAPIValue($value),
        );
      }
    }

    $param_table = new AphrontTableView($param_rows);
    $param_table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $result_rows = array();
    foreach ($result as $key => $value) {
      $result_rows[] = array(
        $key,
        $this->renderAPIValue($value),
      );
    }

    $result_table = new AphrontTableView($result_rows);
    $result_table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $param_panel = new PHUIObjectBoxView();
    $param_panel->setHeaderText(pht('Method Parameters'));
    $param_panel->setTable($param_table);

    $result_panel = new PHUIObjectBoxView();
    $result_panel->setHeaderText(pht('Method Result'));
    $result_panel->setTable($result_table);

    $method_uri = $this->getApplicationURI('method/'.$method.'/');

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($method, $method_uri)
      ->addTextCrumb(pht('Call'));

    $example_panel = null;
    if ($request && $method_implementation) {
      $params = $request->getAllParameters();
      $example_panel = $this->renderExampleBox(
        $method_implementation,
        $params);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $param_panel,
        $result_panel,
        $example_panel,
      ),
      array(
        'title' => pht('Method Call Result'),
      ));
  }

  private function renderAPIValue($value) {
    $json = new PhutilJSON();
    if (is_array($value)) {
      $value = $json->encodeFormatted($value);
    }

    $value = phutil_tag(
      'pre',
      array('style' => 'white-space: pre-wrap;'),
      $value);

    return $value;
  }

  private function decodeConduitParams(
    AphrontRequest $request,
    $method) {

    // Look for parameters from the Conduit API Console, which are encoded
    // as HTTP POST parameters in an array, e.g.:
    //
    //   params[name]=value&params[name2]=value2
    //
    // The fields are individually JSON encoded, since we require users to
    // enter JSON so that we avoid type ambiguity.

    $params = $request->getArr('params', null);
    if ($params !== null) {
      foreach ($params as $key => $value) {
        if ($value == '') {
          // Interpret empty string null (e.g., the user didn't type anything
          // into the box).
          $value = 'null';
        }
        $decoded_value = json_decode($value, true);
        if ($decoded_value === null && strtolower($value) != 'null') {
          // When json_decode() fails, it returns null. This almost certainly
          // indicates that a user was using the web UI and didn't put quotes
          // around a string value. We can either do what we think they meant
          // (treat it as a string) or fail. For now, err on the side of
          // caution and fail. In the future, if we make the Conduit API
          // actually do type checking, it might be reasonable to treat it as
          // a string if the parameter type is string.
          throw new Exception(
            pht(
              "The value for parameter '%s' is not valid JSON. All ".
              "parameters must be encoded as JSON values, including strings ".
              "(which means you need to surround them in double quotes). ".
              "Check your syntax. Value was: %s.",
              $key,
              $value));
        }
        $params[$key] = $decoded_value;
      }

      $metadata = idx($params, '__conduit__', array());
      unset($params['__conduit__']);

      return array($metadata, $params);
    }

    // Otherwise, look for a single parameter called 'params' which has the
    // entire param dictionary JSON encoded.
    $params_json = $request->getStr('params');
    if (strlen($params_json)) {
      $params = null;
      try {
        $params = phutil_json_decode($params_json);
      } catch (PhutilJSONParserException $ex) {
        throw new PhutilProxyException(
          pht(
            "Invalid parameter information was passed to method '%s'.",
            $method),
          $ex);
      }

      $metadata = idx($params, '__conduit__', array());
      unset($params['__conduit__']);

      return array($metadata, $params);
    }

    // If we do not have `params`, assume this is a simple HTTP request with
    // HTTP key-value pairs.
    $params = array();
    $metadata = array();
    foreach ($request->getPassthroughRequestData() as $key => $value) {
      $meta_key = ConduitAPIMethod::getParameterMetadataKey($key);
      if ($meta_key !== null) {
        $metadata[$meta_key] = $value;
      } else {
        $params[$key] = $value;
      }
    }

    return array($metadata, $params);
  }

}
