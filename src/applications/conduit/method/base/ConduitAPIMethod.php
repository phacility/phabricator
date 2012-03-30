<?php

/*
 * Copyright 2012 Facebook, Inc.
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
abstract class ConduitAPIMethod {

  abstract public function getMethodDescription();
  abstract public function defineParamTypes();
  abstract public function defineReturnType();
  abstract public function defineErrorTypes();
  abstract protected function execute(ConduitAPIRequest $request);

  public function __construct() {

  }

  public function getErrorDescription($error_code) {
    return idx($this->defineErrorTypes(), $error_code, 'Unknown Error');
  }

  public function getRequiredScope() {
    // by default, conduit methods are not accessible via OAuth
    return PhabricatorOAuthServerScope::SCOPE_NOT_ACCESSIBLE;
  }

  public function executeMethod(ConduitAPIRequest $request) {
    return $this->execute($request);
  }

  public function getAPIMethodName() {
    return self::getAPIMethodNameFromClassName(get_class($this));
  }

  public static function getClassNameFromAPIMethodName($method_name) {
    $method_fragment = str_replace('.', '_', $method_name);
    return 'ConduitAPI_'.$method_fragment.'_Method';
  }

  public function shouldRequireAuthentication() {
    return true;
  }

  public function shouldAllowUnguardedWrites() {
    return false;
  }

  public static function getAPIMethodNameFromClassName($class_name) {
    $match = null;
    $is_valid = preg_match(
      '/^ConduitAPI_(.*)_Method$/',
      $class_name,
      $match);
    if (!$is_valid) {
      throw new Exception(
        "Parameter '{$class_name}' is not a valid Conduit API method class.");
    }
    $method_fragment = $match[1];
    return str_replace('_', '.', $method_fragment);
  }

  protected function validateHost($host) {
    if (!$host) {
      // If the client doesn't send a host key, don't complain. We should in
      // the future, but this change isn't severe enough to bump the protocol
      // version.

      // TODO: Remove this once the protocol version gets bumped past 2 (i.e.,
      // require the host key be present and valid).
      return;
    }

    // NOTE: Compare domains only so we aren't sensitive to port specification
    // or omission.

    $host = new PhutilURI($host);
    $host = $host->getDomain();

    $self = new PhutilURI(PhabricatorEnv::getURI('/'));
    $self = $self->getDomain();

    if ($self !== $host) {
      throw new Exception(
        "Your client is connecting to this install as '{$host}', but it is ".
        "configured as '{$self}'. The client and server must use the exact ".
        "same URI to identify the install. Edit your .arcconfig or ".
        "phabricator/conf so they agree on the URI for the install.");
    }
  }

}
