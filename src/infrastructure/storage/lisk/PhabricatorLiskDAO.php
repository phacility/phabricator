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

    return PhabricatorEnv::newObjectFromConfig(
      'mysql.implementation',
      array(
        array(
          'user'      => $conf->getUser(),
          'pass'      => $conf->getPassword(),
          'host'      => $conf->getHost(),
          'database'  => $conf->getDatabase(),
          'retries'   => 3,
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


  /**
   * Break a list of escaped SQL statement fragments (e.g., VALUES lists for
   * INSERT, previously built with @{function:qsprintf}) into chunks which will
   * fit under the MySQL 'max_allowed_packet' limit.
   *
   * Chunks are glued together with `$glue`, by default ", ".
   *
   * If a statement is too large to fit within the limit, it is broken into
   * its own chunk (but might fail when the query executes).
   */
  public static function chunkSQL(
    array $fragments,
    $glue = ', ',
    $limit = null) {

    if ($limit === null) {
      // NOTE: Hard-code this at 1MB for now, minus a 10% safety buffer.
      // Eventually we could query MySQL or let the user configure it.
      $limit = (int)((1024 * 1024) * 0.90);
    }

    $result = array();

    $chunk = array();
    $len = 0;
    $glue_len = strlen($glue);
    foreach ($fragments as $fragment) {
      $this_len = strlen($fragment);

      if ($chunk) {
        // Chunks after the first also imply glue.
        $this_len += $glue_len;
      }

      if ($len + $this_len <= $limit) {
        $len += $this_len;
        $chunk[] = $fragment;
      } else {
        if ($chunk) {
          $result[] = $chunk;
        }
        $len = strlen($fragment);
        $chunk = array($fragment);
      }
    }

    if ($chunk) {
      $result[] = $chunk;
    }

    foreach ($result as $key => $fragment_list) {
      $result[$key] = implode($glue, $fragment_list);
    }

    return $result;
  }

}
