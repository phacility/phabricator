<?php

final class PhabricatorStorageManagementAPI {

  private $host;
  private $user;
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

  public function getDatabaseName($fragment) {
    return $this->namespace.'_'.$fragment;
  }

  public function getDatabaseList(array $patches) {
    assert_instances_of($patches, 'PhabricatorStoragePatch');

    $list = array();

    foreach ($patches as $patch) {
      if ($patch->getType() == 'db') {
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
    queryfx(
      $this->getConn(null),
      'CREATE DATABASE IF NOT EXISTS %T COLLATE utf8_general_ci',
      $this->getDatabaseName($fragment));
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

    foreach ($queries as $query) {
      $query = str_replace('{$NAMESPACE}', $this->namespace, $query);
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

}
