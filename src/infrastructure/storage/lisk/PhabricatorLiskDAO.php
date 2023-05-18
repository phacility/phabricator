<?php

/**
 * @task config Configuring Storage
 */
abstract class PhabricatorLiskDAO extends LiskDAO {

  private static $namespaceStack = array();
  private $forcedNamespace;

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
    if ($namespace === null || !strlen($namespace)) {
      throw new Exception(pht('No storage namespace configured!'));
    }
    return $namespace;
  }

  public function setForcedStorageNamespace($namespace) {
    $this->forcedNamespace = $namespace;
    return $this;
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

    $connection = $this->newClusterConnection(
      $this->getApplicationName(),
      $database,
      $mode);

    // TODO: This should be testing if the mode is "r", but that would probably
    // break a lot of things. Perform a more narrow test for readonly mode
    // until we have greater certainty that this works correctly most of the
    // time.
    if ($is_readonly) {
      $connection->setReadOnly(true);
    }

    return $connection;
  }

  private function newClusterConnection($application, $database, $mode) {
    $master = PhabricatorDatabaseRef::getMasterDatabaseRefForApplication(
      $application);

    $master_exception = null;

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

        $master_exception = $master->getConnectionException();
      }
    }

    $replica = PhabricatorDatabaseRef::getReplicaDatabaseRefForApplication(
      $application);
    if ($replica) {
      $connection = $replica->newApplicationConnection($database);
      $connection->setReadOnly(true);
      if ($replica->isReachable($connection)) {
        if ($master_exception) {
          // If we ended up here as the result of a failover, log the
          // exception. This is seriously bad news even if we are able
          // to recover from it.
          $proxy_exception = new PhutilProxyException(
            pht(
              'Failed to connect to master database ("%s"), failing over '.
              'into read-only mode.',
              $database),
            $master_exception);
          phlog($proxy_exception);
        }

        return $connection;
      }
    }

    if (!$master && !$replica) {
      $this->raiseUnconfigured($database);
    }

    $this->raiseUnreachable($database, $master_exception);
  }

  private function raiseImproperWrite($database) {
    throw new PhabricatorClusterImproperWriteException(
      pht(
        'Unable to establish a write-mode connection (to application '.
        'database "%s") because this server is in read-only mode. Whatever '.
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

  private function raiseUnreachable($database, Exception $proxy = null) {
    $message = pht(
      'Unable to establish a connection to any database host '.
      '(while trying "%s"). All masters and replicas are completely '.
      'unreachable.',
      $database);

    if ($proxy) {
      $proxy_message = pht(
        '%s: %s',
        get_class($proxy),
        $proxy->getMessage());
      $message = $message."\n\n".$proxy_message;
    }

    throw new PhabricatorClusterStrandedException($message);
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

  protected function getDatabaseName() {
    if ($this->forcedNamespace) {
      $namespace = $this->forcedNamespace;
    } else {
      $namespace = self::getStorageNamespace();
    }

    return $namespace.'_'.$this->getApplicationName();
  }

  /**
   * Break a list of escaped SQL statement fragments (e.g., VALUES lists for
   * INSERT, previously built with @{function:qsprintf}) into chunks which will
   * fit under the MySQL 'max_allowed_packet' limit.
   *
   * If a statement is too large to fit within the limit, it is broken into
   * its own chunk (but might fail when the query executes).
   */
  public static function chunkSQL(
    array $fragments,
    $limit = null) {

    if ($limit === null) {
      // NOTE: Hard-code this at 1MB for now, minus a 10% safety buffer.
      // Eventually we could query MySQL or let the user configure it.
      $limit = (int)((1024 * 1024) * 0.90);
    }

    $result = array();

    $chunk = array();
    $len = 0;
    $glue_len = strlen(', ');
    foreach ($fragments as $fragment) {
      if ($fragment instanceof PhutilQueryString) {
        $this_len = strlen($fragment->getUnmaskedString());
      } else {
        $this_len = strlen($fragment);
      }

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
        $len = ($this_len - $glue_len);
        $chunk = array($fragment);
      }
    }

    if ($chunk) {
      $result[] = $chunk;
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
      if ($encoding !== null && strlen($encoding)) {
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
