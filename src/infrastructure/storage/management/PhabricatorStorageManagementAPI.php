<?php

final class PhabricatorStorageManagementAPI {

  private $host;
  private $user;
  private $port;
  private $password;
  private $namespace;
  private $conns = array();

  public function setNamespace($namespace) {
    $this->namespace = $namespace;
    PhabricatorLiskDAO::pushStorageNamespace($namespace);
    return $this;
  }

  public function getNamespace() {
    return $this->namespace;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function setPassword($password) {
    $this->password = $password;
    return $this;
  }

  public function getPassword() {
    return $this->password;
  }

  public function setHost($host) {
    $this->host = $host;
    return $this;
  }

  public function getHost() {
    return $this->host;
  }

  public function setPort($port) {
    $this->port = $port;
    return $this;
  }

  public function getPort() {
    return $this->port;
  }

  public function getDatabaseName($fragment) {
    return $this->namespace.'_'.$fragment;
  }

  public function getDatabaseList(array $patches, $only_living = false) {
    assert_instances_of($patches, 'PhabricatorStoragePatch');

    $list = array();

    foreach ($patches as $patch) {
      if ($patch->getType() == 'db') {
        if ($only_living && $patch->isDead()) {
          continue;
        }
        $list[] = $this->getDatabaseName($patch->getName());
      }
    }

    return $list;
  }

  public function getConn($fragment) {
    $database = $this->getDatabaseName($fragment);
    $return = &$this->conns[$this->host][$this->user][$database];
    if (!$return) {
      $return = PhabricatorEnv::newObjectFromConfig(
      'mysql.implementation',
      array(
        array(
          'user'      => $this->user,
          'pass'      => $this->password,
          'host'      => $this->host,
          'port'      => $this->port,
          'database'  => $fragment
            ? $database
            : null,
        ),
      ));
    }
    return $return;
  }

  public function getAppliedPatches() {
    try {
      $applied = queryfx_all(
        $this->getConn('meta_data'),
        'SELECT patch FROM patch_status');
      return ipull($applied, 'patch');
    } catch (AphrontQueryException $ex) {
      return null;
    }
  }

  public function createDatabase($fragment) {
    $info = $this->getCharsetInfo();
    list($charset, $collate_text, $collate_sort) = $info;

    queryfx(
      $this->getConn(null),
      'CREATE DATABASE IF NOT EXISTS %T COLLATE %T',
      $this->getDatabaseName($fragment),
      $collate_text);
  }

  public function createTable($fragment, $table, array $cols) {
    queryfx(
      $this->getConn($fragment),
      'CREATE TABLE IF NOT EXISTS %T.%T (%Q) '.
      'ENGINE=InnoDB, COLLATE utf8_general_ci',
      $this->getDatabaseName($fragment),
      $table,
      implode(', ', $cols));
  }

  public function getLegacyPatches(array $patches) {
    assert_instances_of($patches, 'PhabricatorStoragePatch');

    try {
      $row = queryfx_one(
        $this->getConn('meta_data'),
        'SELECT version FROM %T',
        'schema_version');
      $version = $row['version'];
    } catch (AphrontQueryException $ex) {
      return array();
    }

    $legacy = array();
    foreach ($patches as $key => $patch) {
      if ($patch->getLegacy() !== false && $patch->getLegacy() <= $version) {
        $legacy[] = $key;
      }
    }

    return $legacy;
  }

  public function markPatchApplied($patch) {
    queryfx(
      $this->getConn('meta_data'),
      'INSERT INTO %T (patch, applied) VALUES (%s, %d)',
      'patch_status',
      $patch,
      time());
  }

  public function applyPatch(PhabricatorStoragePatch $patch) {
    $type = $patch->getType();
    $name = $patch->getName();
    switch ($type) {
      case 'db':
        $this->createDatabase($name);
        break;
      case 'sql':
        $this->applyPatchSQL($name);
        break;
      case 'php':
        $this->applyPatchPHP($name);
        break;
      default:
        throw new Exception("Unable to apply patch of type '{$type}'.");
    }
  }

  public function applyPatchSQL($sql) {
    $sql = Filesystem::readFile($sql);
    $queries = preg_split('/;\s+/', $sql);
    $queries = array_filter($queries);

    $conn = $this->getConn(null);

    $charset_info = $this->getCharsetInfo();
    list($charset, $collate_text, $collate_sort) = $charset_info;

    foreach ($queries as $query) {
      $query = str_replace('{$NAMESPACE}', $this->namespace, $query);
      $query = str_replace('{$CHARSET}', $charset, $query);
      $escaped_text = qsprintf($conn, '%T', $collate_text);
      $query = str_replace('{$COLLATE_TEXT}', $escaped_text, $query);
      $escaped_text = qsprintf($conn, '%T', $collate_sort);
      $query = str_replace('{$COLLATE_SORT}', $escaped_text, $query);
      queryfx(
        $conn,
        '%Q',
        $query);
    }
  }

  public function applyPatchPHP($script) {
    $schema_conn = $this->getConn(null);
    require_once $script;
  }

  public function isCharacterSetAvailable($character_set) {
    $conn = $this->getConn(null);

    $result = queryfx_one(
      $conn,
      'SELECT CHARACTER_SET_NAME FROM INFORMATION_SCHEMA.CHARACTER_SETS
        WHERE CHARACTER_SET_NAME = %s',
      $character_set);

    return (bool)$result;
  }

  public function getCharsetInfo() {
    if ($this->isCharacterSetAvailable('utf8mb4')) {
      // If utf8mb4 is available, we use it with the utf8mb4_unicode_ci
      // collation. This is most correct, and will sort properly.

      $charset = 'utf8mb4';
      $collate_text = 'utf8mb4_bin';
      $collate_sort = 'utf8mb4_unicode_ci';
    } else {
      // If utf8mb4 is not available, we use binary. This allows us to store
      // 4-byte unicode characters. This has some tradeoffs:
      //
      // Unicode characters won't sort correctly. There's nothing we can do
      // about this while still supporting 4-byte characters.
      //
      // It's possible that strings will be truncated in the middle of a
      // character on insert. We encourage users to set STRICT_ALL_TABLES
      // to prevent this.

      $charset = 'binary';
      $collate_text = 'binary';
      $collate_sort = 'binary';
    }

    return array($charset, $collate_text, $collate_sort);
  }

}
