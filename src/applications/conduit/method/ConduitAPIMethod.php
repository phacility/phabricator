<?php

/**
 *
 * @task  status  Method Status
 * @group conduit
 */
abstract class ConduitAPIMethod {

  const METHOD_STATUS_STABLE      = 'stable';
  const METHOD_STATUS_UNSTABLE    = 'unstable';
  const METHOD_STATUS_DEPRECATED  = 'deprecated';

  abstract public function getMethodDescription();
  abstract public function defineParamTypes();
  abstract public function defineReturnType();
  abstract public function defineErrorTypes();
  abstract protected function execute(ConduitAPIRequest $request);

  public function __construct() {

  }

  /**
   * Get the status for this method (e.g., stable, unstable or deprecated).
   * Should return a METHOD_STATUS_* constant. By default, methods are
   * "stable".
   *
   * @return const  METHOD_STATUS_* constant.
   * @task status
   */
  public function getMethodStatus() {
    return self::METHOD_STATUS_STABLE;
  }

  /**
   * Optional description to supplement the method status. In particular, if
   * a method is deprecated, you can return a string here describing the reason
   * for deprecation and stable alternatives.
   *
   * @return string|null  Description of the method status, if available.
   * @task status
   */
  public function getMethodStatusDescription() {
    return null;
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
