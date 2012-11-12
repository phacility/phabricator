<?php

/**
 * @task edges  Managing Edges
 * @task config Configuring Storage
 */
abstract class PhabricatorLiskDAO extends LiskDAO {

  private $edges = array();
  private static $namespaceStack = array();


/* -(  Managing Edges  )----------------------------------------------------- */


  /**
   * @task edges
   */
  public function attachEdges(array $edges) {
    foreach ($edges as $type => $type_edges) {
      $this->edges[$type] = $type_edges;
    }
    return $this;
  }


  /**
   * @task edges
   */
  public function getEdges($type) {
    $edges = idx($this->edges, $type);
    if ($edges === null) {
      throw new Exception("Call attachEdges() before getEdges()!");
    }
    return $edges;
  }


  /**
   * @task edges
   */
  public function loadRelativeEdges($type) {
    if (!$this->getInSet()) {
      id(new LiskDAOSet())->addToSet($this);
    }
    $this->getInSet()->loadRelativeEdges($type);
    return $this->getEdges($type);
  }


  /**
   * @task edges
   */
  public function getEdgePHIDs($type) {
    return ipull($this->getEdges($type), 'dst');
  }


/* -(  Configuring Storage  )------------------------------------------------ */

  /**
   * @task config
   */
  public static function pushStorageNamespace($namespace) {
    self::$namespaceStack[] = $namespace;
  }

  /**
   * @task config
   */
  public static function popStorageNamespace() {
    array_pop(self::$namespaceStack);
  }

  /**
   * @task config
   */
  public static function getDefaultStorageNamespace() {
    return PhabricatorEnv::getEnvConfig('storage.default-namespace');
  }

  /**
   * @task config
   */
  public static function getStorageNamespace() {
    $namespace = end(self::$namespaceStack);
    if (!strlen($namespace)) {
      $namespace = self::getDefaultStorageNamespace();
    }
    if (!strlen($namespace)) {
      throw new Exception("No storage namespace configured!");
    }
    return $namespace;
  }

  /**
   * @task config
   */
  public function establishLiveConnection($mode) {
    $namespace = self::getStorageNamespace();

    $conf = PhabricatorEnv::newObjectFromConfig(
      'mysql.configuration-provider',
      array($this, $mode, $namespace));

    $retries = PhabricatorEnv::getEnvConfig('mysql.connection-retries');
    return PhabricatorEnv::newObjectFromConfig(
      'mysql.implementation',
      array(
        array(
          'user'      => $conf->getUser(),
          'pass'      => $conf->getPassword(),
          'host'      => $conf->getHost(),
          'database'  => $conf->getDatabase(),
          'retries'   => $retries,
        ),
      ));
  }

  /**
   * @task config
   */
  public function getTableName() {
    $str = 'phabricator';
    $len = strlen($str);

    $class = strtolower(get_class($this));
    if (!strncmp($class, $str, $len)) {
      $class = substr($class, $len);
    }
    $app = $this->getApplicationName();
    if (!strncmp($class, $app, strlen($app))) {
      $class = substr($class, strlen($app));
    }

    if (strlen($class)) {
      return $app.'_'.$class;
    } else {
      return $app;
    }
  }

  /**
   * @task config
   */
  abstract public function getApplicationName();

  protected function getConnectionNamespace() {
    return self::getStorageNamespace().'_'.$this->getApplicationName();
  }
}
