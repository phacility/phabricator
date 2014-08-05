<?php

/**
 * @task  status  Method Status
 * @task  pager   Paging Results
 */
abstract class ConduitAPIMethod
  extends Phobject
  implements PhabricatorPolicyInterface {

  const METHOD_STATUS_STABLE      = 'stable';
  const METHOD_STATUS_UNSTABLE    = 'unstable';
  const METHOD_STATUS_DEPRECATED  = 'deprecated';

  abstract public function getMethodDescription();
  abstract public function defineParamTypes();
  abstract public function defineReturnType();
  abstract public function defineErrorTypes();
  abstract protected function execute(ConduitAPIRequest $request);

  public function __construct() {}

  /**
   * This is mostly for compatibility with
   * @{class:PhabricatorCursorPagedPolicyAwareQuery}.
   */
  public function getID() {
    return $this->getAPIMethodName();
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

  public abstract function getAPIMethodName();

  /**
   * Return a key which sorts methods by application name, then method status,
   * then method name.
   */
  public function getSortOrder() {
    $name = $this->getAPIMethodName();

    $map = array(
      ConduitAPIMethod::METHOD_STATUS_STABLE      => 0,
      ConduitAPIMethod::METHOD_STATUS_UNSTABLE    => 1,
      ConduitAPIMethod::METHOD_STATUS_DEPRECATED  => 2,
    );
    $ord = idx($map, $this->getMethodStatus(), 0);

    list($head, $tail) = explode('.', $name, 2);

    return "{$head}.{$ord}.{$tail}";
  }

  public function getApplicationName() {
    return head(explode('.', $this->getAPIMethodName(), 2));
  }

  public static function getConduitMethod($method_name) {
    static $method_map = null;

    if ($method_map === null) {
      $methods = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      foreach ($methods as $method) {
        $name = $method->getAPIMethodName();

        if (empty($method_map[$name])) {
          $method_map[$name] = $method;
          continue;
        }

        $orig_class = get_class($method_map[$name]);
        $this_class = get_class($method);
        throw new Exception(
          "Two Conduit API method classes ({$orig_class}, {$this_class}) ".
          "both have the same method name ({$name}). API methods ".
          "must have unique method names.");
      }
    }

    return idx($method_map, $method_name);
  }

  public function shouldRequireAuthentication() {
    return true;
  }

  public function shouldAllowPublic() {
    return false;
  }

  public function shouldAllowUnguardedWrites() {
    return false;
  }


  /**
   * Optionally, return a @{class:PhabricatorApplication} which this call is
   * part of. The call will be disabled when the application is uninstalled.
   *
   * @return PhabricatorApplication|null  Related application.
   */
  public function getApplication() {
    return null;
  }

  protected function formatStringConstants($constants) {
    foreach ($constants as $key => $value) {
      $constants[$key] = '"'.$value.'"';
    }
    $constants = implode(', ', $constants);
    return 'string-constant<'.$constants.'>';
  }


/* -(  Paging Results  )----------------------------------------------------- */


  /**
   * @task pager
   */
  protected function getPagerParamTypes() {
    return array(
      'before' => 'optional string',
      'after'  => 'optional string',
      'limit'  => 'optional int (default = 100)',
    );
  }


  /**
   * @task pager
   */
  protected function newPager(ConduitAPIRequest $request) {
    $limit = $request->getValue('limit', 100);
    $limit = min(1000, $limit);
    $limit = max(1, $limit);

    $pager = id(new AphrontCursorPagerView())
      ->setPageSize($limit);

    $before_id = $request->getValue('before');
    if ($before_id !== null) {
      $pager->setBeforeID($before_id);
    }

    $after_id = $request->getValue('after');
    if ($after_id !== null) {
      $pager->setAfterID($after_id);
    }

    return $pager;
  }


  /**
   * @task pager
   */
  protected function addPagerResults(
    array $results,
    AphrontCursorPagerView $pager) {

    $results['cursor'] = array(
      'limit' => $pager->getPageSize(),
      'after' => $pager->getNextPageID(),
      'before' => $pager->getPrevPageID(),
    );

    return $results;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getPHID() {
    return null;
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    // Application methods get application visibility; other methods get open
    // visibility.

    $application = $this->getApplication();
    if ($application) {
      return $application->getPolicy($capability);
    }

    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if (!$this->shouldRequireAuthentication()) {
      // Make unauthenticated methods universally visible.
      return true;
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

  protected function hasApplicationCapability(
    $capability,
    PhabricatorUser $viewer) {

    $application = $this->getApplication();

    if (!$application) {
      return false;
    }

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $application,
      $capability);
  }

  protected function requireApplicationCapability(
    $capability,
    PhabricatorUser $viewer) {

    $application = $this->getApplication();
    if (!$application) {
      return;
    }

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $this->getApplication(),
      $capability);
  }

}
