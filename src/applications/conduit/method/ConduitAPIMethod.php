<?php

/**
 * @task info Method Information
 * @task status Method Status
 * @task pager Paging Results
 */
abstract class ConduitAPIMethod
  extends Phobject
  implements PhabricatorPolicyInterface {

  private $viewer;

  const METHOD_STATUS_STABLE      = 'stable';
  const METHOD_STATUS_UNSTABLE    = 'unstable';
  const METHOD_STATUS_DEPRECATED  = 'deprecated';

  const SCOPE_NEVER = 'scope.never';
  const SCOPE_ALWAYS = 'scope.always';

  /**
   * Get a short, human-readable text summary of the method.
   *
   * @return string Short summary of method.
   * @task info
   */
  public function getMethodSummary() {
    return $this->getMethodDescription();
  }


  /**
   * Get a detailed description of the method.
   *
   * This method should return remarkup.
   *
   * @return string Detailed description of the method.
   * @task info
   */
  abstract public function getMethodDescription();

  public function getMethodDocumentation() {
    return null;
  }

  abstract protected function defineParamTypes();
  abstract protected function defineReturnType();

  protected function defineErrorTypes() {
    return array();
  }

  abstract protected function execute(ConduitAPIRequest $request);

  public function isInternalAPI() {
    return false;
  }

  public function getParamTypes() {
    $types = $this->defineParamTypes();

    $query = $this->newQueryObject();
    if ($query) {
      $types['order'] = 'optional order';
      $types += $this->getPagerParamTypes();
    }

    return $types;
  }

  public function getReturnType() {
    return $this->defineReturnType();
  }

  public function getErrorTypes() {
    return $this->defineErrorTypes();
  }

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
    return idx($this->getErrorTypes(), $error_code, pht('Unknown Error'));
  }

  public function getRequiredScope() {
    return self::SCOPE_NEVER;
  }

  public function executeMethod(ConduitAPIRequest $request) {
    $this->setViewer($request->getUser());

    return $this->execute($request);
  }

  abstract public function getAPIMethodName();

  /**
   * Return a key which sorts methods by application name, then method status,
   * then method name.
   */
  public function getSortOrder() {
    $name = $this->getAPIMethodName();

    $map = array(
      self::METHOD_STATUS_STABLE      => 0,
      self::METHOD_STATUS_UNSTABLE    => 1,
      self::METHOD_STATUS_DEPRECATED  => 2,
    );
    $ord = idx($map, $this->getMethodStatus(), 0);

    list($head, $tail) = explode('.', $name, 2);

    return "{$head}.{$ord}.{$tail}";
  }

  public static function getMethodStatusMap() {
    $map = array(
      self::METHOD_STATUS_STABLE => pht('Stable'),
      self::METHOD_STATUS_UNSTABLE => pht('Unstable'),
      self::METHOD_STATUS_DEPRECATED => pht('Deprecated'),
    );

    return $map;
  }

  public function getApplicationName() {
    return head(explode('.', $this->getAPIMethodName(), 2));
  }

  public static function loadAllConduitMethods() {
    return self::newClassMapQuery()->execute();
  }

  private static function newClassMapQuery() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getAPIMethodName');
  }

  public static function getConduitMethod($method_name) {
    return id(new PhabricatorCachedClassMapQuery())
      ->setClassMapQuery(self::newClassMapQuery())
      ->setMapKeyMethod('getAPIMethodName')
      ->loadClass($method_name);
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

  public static function getParameterMetadataKey($key) {
    if (strncmp($key, 'api.', 4) === 0) {
      // All keys passed beginning with "api." are always metadata keys.
      return substr($key, 4);
    } else {
      switch ($key) {
        // These are real keys which always belong to request metadata.
        case 'access_token':
        case 'scope':
        case 'output':

        // This is not a real metadata key; it is included here only to
        // prevent Conduit methods from defining it.
        case '__conduit__':

        // This is prevented globally as a blanket defense against OAuth
        // redirection attacks. It is included here to stop Conduit methods
        // from defining it.
        case 'code':

        // This is not a real metadata key, but the presence of this
        // parameter triggers an alternate request decoding pathway.
        case 'params':
          return $key;
      }
    }

    return null;
  }

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
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


/* -(  Implementing Query Methods  )----------------------------------------- */


  public function newQueryObject() {
    return null;
  }


  protected function newQueryForRequest(ConduitAPIRequest $request) {
    $query = $this->newQueryObject();

    if (!$query) {
      throw new Exception(
        pht(
          'You can not call newQueryFromRequest() in this method ("%s") '.
          'because it does not implement newQueryObject().',
          get_class($this)));
    }

    if (!($query instanceof PhabricatorCursorPagedPolicyAwareQuery)) {
      throw new Exception(
        pht(
          'Call to method newQueryObject() did not return an object of class '.
          '"%s".',
          'PhabricatorCursorPagedPolicyAwareQuery'));
    }

    $query->setViewer($request->getUser());

    $order = $request->getValue('order');
    if ($order !== null) {
      if (is_scalar($order)) {
        $query->setOrder($order);
      } else {
        $query->setOrderVector($order);
      }
    }

    return $query;
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
