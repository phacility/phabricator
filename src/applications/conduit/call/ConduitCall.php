<?php

/**
 * Run a conduit method in-process, without requiring HTTP requests. Usage:
 *
 *   $call = new ConduitCall('method.name', array('param' => 'value'));
 *   $call->setUser($user);
 *   $result = $call->execute();
 *
 */
final class ConduitCall extends Phobject {

  private $method;
  private $handler;
  private $request;
  private $user;

  public function __construct($method, array $params, $strictly_typed = true) {
    $this->method = $method;
    $this->handler = $this->buildMethodHandler($method);

    $param_types = $this->handler->getParamTypes();

    foreach ($param_types as $key => $spec) {
      if (ConduitAPIMethod::getParameterMetadataKey($key) !== null) {
        throw new ConduitException(
          pht(
            'API Method "%s" defines a disallowed parameter, "%s". This '.
            'parameter name is reserved.',
            $method,
            $key));
      }
    }

    $invalid_params = array_diff_key($params, $param_types);
    if ($invalid_params) {
      throw new ConduitException(
        pht(
          'API Method "%s" does not define these parameters: %s.',
          $method,
          "'".implode("', '", array_keys($invalid_params))."'"));
    }

    $this->request = new ConduitAPIRequest($params, $strictly_typed);
  }

  public function getAPIRequest() {
    return $this->request;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function shouldRequireAuthentication() {
    return $this->handler->shouldRequireAuthentication();
  }

  public function shouldAllowUnguardedWrites() {
    return $this->handler->shouldAllowUnguardedWrites();
  }

  public function getErrorDescription($code) {
    return $this->handler->getErrorDescription($code);
  }

  public function execute() {
    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 'conduit',
        'method' => $this->method,
      ));

    try {
      $result = $this->executeMethod();
    } catch (Exception $ex) {
      $profiler->endServiceCall($call_id, array());
      throw $ex;
    }

    $profiler->endServiceCall($call_id, array());
    return $result;
  }

  private function executeMethod() {
    $user = $this->getUser();
    if (!$user) {
      $user = new PhabricatorUser();
    }

    $this->request->setUser($user);

    if (!$this->shouldRequireAuthentication()) {
      // No auth requirement here.
    } else {

      $allow_public = $this->handler->shouldAllowPublic() &&
                      PhabricatorEnv::getEnvConfig('policy.allow-public');
      if (!$allow_public) {
        if (!$user->isLoggedIn() && !$user->isOmnipotent()) {
          // TODO: As per below, this should get centralized and cleaned up.
          throw new ConduitException('ERR-INVALID-AUTH');
        }
      }

      // TODO: This would be slightly cleaner by just using a Query, but the
      // Conduit auth workflow requires the Call and User be built separately.
      // Just do it this way for the moment.
      $application = $this->handler->getApplication();
      if ($application) {
        $can_view = PhabricatorPolicyFilter::hasCapability(
          $user,
          $application,
          PhabricatorPolicyCapability::CAN_VIEW);

        if (!$can_view) {
          throw new ConduitException(
            pht(
              'You do not have access to the application which provides this '.
              'API method.'));
        }
      }
    }

    return $this->handler->executeMethod($this->request);
  }

  protected function buildMethodHandler($method_name) {
    $method = ConduitAPIMethod::getConduitMethod($method_name);

    if (!$method) {
      throw new ConduitMethodDoesNotExistException($method_name);
    }

    $application = $method->getApplication();
    if ($application && !$application->isInstalled()) {
      $app_name = $application->getName();
      throw new ConduitApplicationNotInstalledException($method, $app_name);
    }

    return $method;
  }

  public function getMethodImplementation() {
    return $this->handler;
  }


}
