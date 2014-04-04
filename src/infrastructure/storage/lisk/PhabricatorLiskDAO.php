<?php

/**
 * @task config Configuring Storage
 */
abstract class PhabricatorLiskDAO extends LiskDAO {

  private static $namespaceStack = array();

  const ATTACHABLE = "<attachable>";

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
          'port'      => $conf->getPort(),
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
    return phutil_utf8ize($string);
  }

  public function delete() {

    // TODO: We should make some reasonable effort to destroy related
    // infrastructure objects here, like edges, transactions, custom field
    // storage, flags, Phrequent tracking, tokens, etc. This doesn't need to
    // be exhaustive, but we can get a lot of it pretty easily.

    return parent::delete();
  }

}
