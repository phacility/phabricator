<?php

/**
 * @task config Configuring Storage
 */
abstract class PhabricatorLiskDAO extends LiskDAO {

  private static $namespaceStack = array();

  const ATTACHABLE = '<attachable>';
  const CONFIG_APPLICATION_SERIALIZERS = 'phabricator/serializers';

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
      throw new Exception(pht('No storage namespace configured!'));
    }
    return $namespace;
  }

  /**
   * @task config
   */
  protected function establishLiveConnection($mode) {
    $namespace = self::getStorageNamespace();
    $database = $namespace.'_'.$this->getApplicationName();

    $is_readonly = PhabricatorEnv::isReadOnly();

    if ($is_readonly && ($mode != 'r')) {
      $this->raiseImproperWrite($database);
    }

    $is_cluster = (bool)PhabricatorEnv::getEnvConfig('cluster.databases');
    if ($is_cluster) {
      $connection = $this->newClusterConnection($database, $mode);
    } else {
      $connection = $this->newBasicConnection($database, $mode, $namespace);
    }

    // TODO: This should be testing if the mode is "r", but that would probably
    // break a lot of things. Perform a more narrow test for readonly mode
    // until we have greater certainty that this works correctly most of the
    // time.
    if ($is_readonly) {
      $connection->setReadOnly(true);
    }

    // Unless this is a script running from the CLI:
    //   - (T10849) Prevent any query from running for more than 30 seconds.
    //   - (T11672) Use persistent connections.
    if (php_sapi_name() != 'cli') {

      // TODO: For now, disable this until after T11044: it's better at high
      // load, but causes us to use slightly more connections at low load and
      // is pushing users over limits like MySQL "max_connections".
      $use_persistent = false;

      $connection
        ->setQueryTimeout(30)
        ->setPersistent($use_persistent);
    }

    return $connection;
  }

  private function newBasicConnection($database, $mode, $namespace) {
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
          'port'      => $conf->getPort(),
          'database'  => $database,
          'retries'   => 3,
          'timeout' => 10,
        ),
      ));
  }

  private function newClusterConnection($database, $mode) {
    $master = PhabricatorDatabaseRef::getMasterDatabaseRef();

    if ($master && !$master->isSevered()) {
      $connection = $master->newApplicationConnection($database);
      if ($master->isReachable($connection)) {
        return $connection;
      } else {
        if ($mode == 'w') {
          $this->raiseImpossibleWrite($database);
        }
        PhabricatorEnv::setReadOnly(
          true,
          PhabricatorEnv::READONLY_UNREACHABLE);
      }
    }

    $replica = PhabricatorDatabaseRef::getReplicaDatabaseRef();
    if ($replica) {
      $connection = $replica->newApplicationConnection($database);
      $connection->setReadOnly(true);
      if ($replica->isReachable($connection)) {
        return $connection;
      }
    }

    if (!$master && !$replica) {
      $this->raiseUnconfigured($database);
    }

    $this->raiseUnreachable($database);
  }

  private function raiseImproperWrite($database) {
    throw new PhabricatorClusterImproperWriteException(
      pht(
        'Unable to establish a write-mode connection (to application '.
        'database "%s") because Phabricator is in read-only mode. Whatever '.
        'you are trying to do does not function correctly in read-only mode.',
        $database));
  }

  private function raiseImpossibleWrite($database) {
    throw new PhabricatorClusterImpossibleWriteException(
      pht(
        'Unable to connect to master database ("%s"). This is a severe '.
        'failure; your request did not complete.',
        $database));
  }

  private function raiseUnconfigured($database) {
    throw new Exception(
      pht(
        'Unable to establish a connection to any database host '.
        '(while trying "%s"). No masters or replicas are configured.',
        $database));
  }

  private function raiseUnreachable($database) {
    throw new PhabricatorClusterStrandedException(
      pht(
        'Unable to establish a connection to any database host '.
        '(while trying "%s"). All masters and replicas are completely '.
        'unreachable.',
        $database));
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

  protected function assertAttached($property) {
    if ($property === self::ATTACHABLE) {
      throw new PhabricatorDataNotAttachedException($this);
    }
    return $property;
  }

  protected function assertAttachedKey($value, $key) {
    $this->assertAttached($value);
    if (!array_key_exists($key, $value)) {
      throw new PhabricatorDataNotAttachedException($this);
    }
    return $value[$key];
  }

  protected function detectEncodingForStorage($string) {
    return phutil_is_utf8($string) ? 'utf8' : null;
  }

  protected function getUTF8StringFromStorage($string, $encoding) {
    if ($encoding == 'utf8') {
      return $string;
    }

    if (function_exists('mb_detect_encoding')) {
      if (strlen($encoding)) {
        $try_encodings = array(
          $encoding,
        );
      } else {
        // TODO: This is pretty much a guess, and probably needs to be
        // configurable in the long run.
        $try_encodings = array(
          'JIS',
          'EUC-JP',
          'SJIS',
          'ISO-8859-1',
        );
      }

      $guess = mb_detect_encoding($string, $try_encodings);
      if ($guess) {
        return mb_convert_encoding($string, 'UTF-8', $guess);
      }
    }

    return phutil_utf8ize($string);
  }

  protected function willReadData(array &$data) {
    parent::willReadData($data);

    static $custom;
    if ($custom === null) {
      $custom = $this->getConfigOption(self::CONFIG_APPLICATION_SERIALIZERS);
    }

    if ($custom) {
      foreach ($custom as $key => $serializer) {
        $data[$key] = $serializer->willReadValue($data[$key]);
      }
    }
  }

  protected function willWriteData(array &$data) {
    static $custom;
    if ($custom === null) {
      $custom = $this->getConfigOption(self::CONFIG_APPLICATION_SERIALIZERS);
    }

    if ($custom) {
      foreach ($custom as $key => $serializer) {
        $data[$key] = $serializer->willWriteValue($data[$key]);
      }
    }

    parent::willWriteData($data);
  }


}
