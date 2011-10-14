<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group conduit
 */
class PhabricatorConduitAPIController
  extends PhabricatorConduitController {

  public function shouldRequireLogin() {
    return false;
  }

  private $method;

  public function willProcessRequest(array $data) {
    $this->method = $data['method'];
    return $this;
  }

  public function processRequest() {
    $time_start = microtime(true);
    $request = $this->getRequest();

    $method = $this->method;

    $method_class = ConduitAPIMethod::getClassNameFromAPIMethodName($method);
    $api_request = null;

    $log = new PhabricatorConduitMethodCallLog();
    $log->setMethod($method);
    $metadata = array();

    try {

      if (!class_exists($method_class)) {
        throw new Exception(
          "Unable to load the implementation class for method '{$method}'. ".
          "You may have misspelled the method, need to define ".
          "'{$method_class}', or need to run 'arc build'.");
      }

      $class_info = new ReflectionClass($method_class);
      if ($class_info->isAbstract()) {
        throw new Exception(
          "Method '{$method}' is not valid; the implementation is an abstract ".
          "base class.");
      }

      $method_handler = newv($method_class, array());

      if (isset($_REQUEST['params']) && is_array($_REQUEST['params'])) {
        $params_post = $request->getArr('params');
        foreach ($params_post as $key => $value) {
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
              "The value for parameter '{$key}' is not valid JSON. All ".
              "parameters must be encoded as JSON values, including strings ".
              "(which means you need to surround them in double quotes). ".
              "Check your syntax. Value was: {$value}");
          }
          $params_post[$key] = $decoded_value;
        }
        $params = $params_post;
      } else {
        $params_json = $request->getStr('params');
        if (!strlen($params_json)) {
          $params = array();
        } else {
          $params = json_decode($params_json, true);
          if (!is_array($params)) {
            throw new Exception(
              "Invalid parameter information was passed to method ".
              "'{$method}', could not decode JSON serialization.");
          }
        }
      }

      $metadata = idx($params, '__conduit__', array());
      unset($params['__conduit__']);

      $result = null;

      $api_request = new ConduitAPIRequest($params);

      $allow_unguarded_writes = false;
      $auth_error = null;
      if ($method_handler->shouldRequireAuthentication()) {
        $auth_error = $this->authenticateUser($api_request, $metadata);
        // If we've explicitly authenticated the user here and either done
        // CSRF validation or are using a non-web authentication mechanism.
        $allow_unguarded_writes = true;
      }

      if ($method_handler->shouldAllowUnguardedWrites()) {
        $allow_unguarded_writes = true;
      }

      if ($auth_error === null) {
        if ($allow_unguarded_writes) {
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        }
        try {
          $result = $method_handler->executeMethod($api_request);
          $error_code = null;
          $error_info = null;
        } catch (ConduitException $ex) {
          $result = null;
          $error_code = $ex->getMessage();
          $error_info = $method_handler->getErrorDescription($error_code);
        }
        if ($allow_unguarded_writes) {
          unset($unguarded);
        }
      } else {
        list($error_code, $error_info) = $auth_error;
      }
    } catch (Exception $ex) {
      phlog($ex);
      $result = null;
      $error_code = 'ERR-CONDUIT-CORE';
      $error_info = $ex->getMessage();
    }

    $time_end = microtime(true);

    $connection_id = null;
    if (idx($metadata, 'connectionID')) {
      $connection_id = $metadata['connectionID'];
    } else if (($method == 'conduit.connect') && $result) {
      $connection_id = idx($result, 'connectionID');
    }

    $log->setConnectionID($connection_id);
    $log->setError((string)$error_code);
    $log->setDuration(1000000 * ($time_end - $time_start));

    // TODO: This is a hack, but the insert is comparatively expensive and
    // we only really care about having these logs for real CLI clients, if
    // even that.
    if (empty($metadata['authToken'])) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $log->save();
      unset($unguarded);
    }

    $result = array(
      'result'      => $result,
      'error_code'  => $error_code,
      'error_info'  => $error_info,
    );

    switch ($request->getStr('output')) {
      case 'human':
        return $this->buildHumanReadableResponse(
          $method,
          $api_request,
          $result);
      case 'json':
      default:
        return id(new AphrontFileResponse())
          ->setMimeType('application/json')
          ->setContent('for(;;);'.json_encode($result));
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
    array $metadata) {

    $request = $this->getRequest();

    if ($request->getUser()->getPHID()) {
      $request->validateCSRF();
      $api_request->setUser($request->getUser());
      return null;
    }

    // Handle sessionless auth. TOOD: This is super messy.
    if (isset($metadata['authUser'])) {
      $user = id(new PhabricatorUser())->loadOneWhere(
        'userName = %s',
        $metadata['authUser']);
      if (!$user) {
        return array(
          'ERR-INVALID-AUTH',
          'Authentication is invalid.',
        );
      }
      $token = idx($metadata, 'authToken');
      $signature = idx($metadata, 'authSignature');
      $certificate = $user->getConduitCertificate();
      if (sha1($token.$certificate) !== $signature) {
        return array(
          'ERR-INVALID-AUTH',
          'Authentication is invalid.',
        );
      }
      $api_request->setUser($user);
      return null;
    }

    $session_key = idx($metadata, 'sessionKey');
    if (!$session_key) {
      return array(
        'ERR-INVALID-SESSION',
        'Session key is not present.'
      );
    }

    $session = queryfx_one(
      id(new PhabricatorUser())->establishConnection('r'),
      'SELECT * FROM %T WHERE sessionKey = %s',
      PhabricatorUser::SESSION_TABLE,
      $session_key);
    if (!$session) {
      return array(
        'ERR-INVALID-SESSION',
        'Session key is invalid.',
      );
    }

    // TODO: Make sessions timeout.
    // TODO: When we pull a session, read connectionID from the session table.

    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $session['userPHID']);
    if (!$user) {
      return array(
        'ERR-INVALID-SESSION',
        'Session is for nonexistent user.',
      );
    }

    $api_request->setUser($user);
    return null;
  }

  private function buildHumanReadableResponse(
    $method,
    ConduitAPIRequest $request = null,
    $result = null) {

    $param_rows = array();
    $param_rows[] = array('Method', $this->renderAPIValue($method));
    if ($request) {
      foreach ($request->getAllParameters() as $key => $value) {
        $param_rows[] = array(
          phutil_escape_html($key),
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
        phutil_escape_html($key),
        $this->renderAPIValue($value),
      );
    }

    $result_table = new AphrontTableView($result_rows);
    $result_table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $param_panel = new AphrontPanelView();
    $param_panel->setHeader('Method Parameters');
    $param_panel->appendChild($param_table);

    $result_panel = new AphrontPanelView();
    $result_panel->setHeader('Method Result');
    $result_panel->appendChild($result_table);

    return $this->buildStandardPageResponse(
      array(
        $param_panel,
        $result_panel,
      ),
      array(
        'title' => 'Method Call Result',
      ));
  }

  private function renderAPIValue($value) {
    $json = new PhutilJSON();
    if (is_array($value)) {
      $value = $json->encodeFormatted($value);
      $value = phutil_escape_html($value);
    } else {
      $value = phutil_escape_html($value);
    }

    $value = '<pre style="white-space: pre-wrap;">'.$value.'</pre>';

    return $value;
  }

}
