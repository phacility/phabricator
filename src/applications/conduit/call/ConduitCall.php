<?php

/**
 * Run a conduit method in-process, without requiring HTTP requests. Usage:
 *
 *   $call = new ConduitCall('method.name', array('param' => 'value'));
 *   $call->setUser($user);
 *   $result = $call->execute();
 *
 */
final class ConduitCall {

  private $method;
  private $request;
  private $user;
  private $servers;
  private $forceLocal;

  public function __construct($method, array $params) {
    $this->method     = $method;
    $this->handler    = $this->buildMethodHandler($method);
    $this->servers    = PhabricatorEnv::getEnvConfig('conduit.servers');
    $this->forceLocal = false;

    $invalid_params = array_diff_key(
      $params,
      $this->handler->defineParamTypes());
    if ($invalid_params) {
      throw new ConduitException(
        "Method '{$method}' doesn't define these parameters: '".
        implode("', '", array_keys($invalid_params))."'.");
    }

    if ($this->servers) {
      $current_host = AphrontRequest::getHTTPHeader('HOST');
      foreach ($this->servers as $server) {
        if ($current_host === id(new PhutilURI($server))->getDomain()) {
          $this->forceLocal = true;
          break;
        }
      }
    }

    $this->request = new ConduitAPIRequest($params);
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function setForceLocal($force_local) {
    $this->forceLocal = $force_local;
    return $this;
  }

  public function shouldForceLocal() {
    return $this->forceLocal;
  }

  public function shouldRequireAuthentication() {
    return $this->handler->shouldRequireAuthentication();
  }

  public function shouldAllowUnguardedWrites() {
    return $this->handler->shouldAllowUnguardedWrites();
  }

  public function getRequiredScope() {
    return $this->handler->getRequiredScope();
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

    if (!$this->shouldForceLocal() && $this->servers) {
      $server = $this->pickRandomServer($this->servers);
      $client = new ConduitClient($server);
      $params = $this->request->getAllParameters();

      $params['__conduit__']['isProxied'] = true;

      if ($this->handler->shouldRequireAuthentication()) {
        $client->callMethodSynchronous(
        'conduit.connect',
        array(
             'client'            => 'PhabricatorConduit',
             'clientVersion'     => '1.0',
             'user'              => $this->getUser()->getUserName(),
             'certificate'       => $this->getUser()->getConduitCertificate(),
             '__conduit__'       => $params['__conduit__'],
        ));
      }

      return $client->callMethodSynchronous(
        $this->method,
        $params);
    } else {
      return $this->handler->executeMethod($this->request);
    }
  }

  protected function pickRandomServer($servers) {
    return $servers[array_rand($servers)];
  }

  protected function buildMethodHandler($method) {
    $method_class = ConduitAPIMethod::getClassNameFromAPIMethodName($method);

    // Test if the method exists.
    $ok = false;
    try {
      $ok = class_exists($method_class);
    } catch (Exception $ex) {
      // Discard, we provide a more specific exception below.
    }
    if (!$ok) {
      throw new ConduitException(
        "Conduit method '{$method}' does not exist.");
    }

    $class_info = new ReflectionClass($method_class);
    if ($class_info->isAbstract()) {
      throw new ConduitException(
        "Method '{$method}' is not valid; the implementation is an abstract ".
        "base class.");
    }

    $method = newv($method_class, array());

    if (!($method instanceof ConduitAPIMethod)) {
      throw new ConduitException(
        "Method '{$method_class}' is not valid; the implementation must be ".
        "a subclass of ConduitAPIMethod.");
    }

    $application = $method->getApplication();
    if ($application && !$application->isInstalled()) {
      $app_name = $application->getName();
      throw new ConduitException(
        "Method '{$method_class}' belongs to application '{$app_name}', ".
        "which is not installed.");
    }

    return $method;
  }


}
