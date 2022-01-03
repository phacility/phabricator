<?php

final class PhabricatorStorageManagementAPI extends Phobject {

  private $ref;
  private $host;
  private $user;
  private $port;
  private $password;
  private $namespace;
  private $conns = array();
  private $disableUTF8MB4;

  const CHARSET_DEFAULT = 'CHARSET';
  const CHARSET_SORT = 'CHARSET_SORT';
  const CHARSET_FULLTEXT = 'CHARSET_FULLTEXT';
  const COLLATE_TEXT = 'COLLATE_TEXT';
  const COLLATE_SORT = 'COLLATE_SORT';
  const COLLATE_FULLTEXT = 'COLLATE_FULLTEXT';

  const TABLE_STATUS = 'patch_status';
  const TABLE_HOSTSTATE = 'hoststate';

  public function setDisableUTF8MB4($disable_utf8_mb4) {
    $this->disableUTF8MB4 = $disable_utf8_mb4;
    return $this;
  }

  public function getDisableUTF8MB4() {
    return $this->disableUTF8MB4;
  }

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

  public function setRef(PhabricatorDatabaseRef $ref) {
    $this->ref = $ref;
    return $this;
  }

  public function getRef() {
    return $this->ref;
  }

  public function getDatabaseName($fragment) {
    return $this->namespace.'_'.$fragment;
  }

  public function getInternalDatabaseName($name) {
    $namespace = $this->getNamespace();

    $prefix = $namespace.'_';
    if (strncmp($name, $prefix, strlen($prefix))) {
      return null;
    }

    return substr($name, strlen($prefix));
  }

  public function getDisplayName() {
    return $this->getRef()->getDisplayName();
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
      $return = PhabricatorDatabaseRef::newRawConnection(
        array(
          'user'      => $this->user,
          'pass'      => $this->password,
          'host'      => $this->host,
          'port'      => $this->port,
          'database'  => $fragment
            ? $database
            : null,
        ));
    }
    return $return;
  }

  public function getAppliedPatches() {
    try {
      $applied = queryfx_all(
        $this->getConn('meta_data'),
        'SELECT patch FROM %T',
        self::TABLE_STATUS);
      return ipull($applied, 'patch');
    } catch (AphrontAccessDeniedQueryException $ex) {
      throw new PhutilProxyException(
        pht(
          'Failed while trying to read schema status: the database "%s" '.
          'exists, but the current user ("%s") does not have permission to '.
          'access it. GRANT the current user more permissions, or use a '.
          'different user.',
          $this->getDatabaseName('meta_data'),
          $this->getUser()),
        $ex);
    } catch (AphrontQueryException $ex) {
      return null;
    }
  }

  public function getPatchDurations() {
    try {
      $rows = queryfx_all(
        $this->getConn('meta_data'),
        'SELECT patch, duration FROM %T WHERE duration IS NOT NULL',
        self::TABLE_STATUS);
      return ipull($rows, 'duration', 'patch');
    } catch (AphrontQueryException $ex) {
      return array();
    }
  }

  public function createDatabase($fragment) {
    $info = $this->getCharsetInfo();

    queryfx(
      $this->getConn(null),
      'CREATE DATABASE IF NOT EXISTS %T COLLATE %T',
      $this->getDatabaseName($fragment),
      $info[self::COLLATE_TEXT]);
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

  public function markPatchApplied($patch, $duration = null) {
    $conn = $this->getConn('meta_data');

    queryfx(
      $conn,
      'INSERT INTO %T (patch, applied) VALUES (%s, %d)',
      self::TABLE_STATUS,
      $patch,
      time());

    // We didn't add this column for a long time, so it may not exist yet.
    if ($duration !== null) {
      try {
        queryfx(
          $conn,
          'UPDATE %T SET duration = %d WHERE patch = %s',
          self::TABLE_STATUS,
          (int)floor($duration * 1000000),
          $patch);
      } catch (AphrontQueryException $ex) {
        // Just ignore this, as it almost certainly indicates that we just
        // don't have the column yet.
      }
    }
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
        throw new Exception(pht("Unable to apply patch of type '%s'.", $type));
    }
  }

  public function applyPatchSQL($sql) {
    $sql = Filesystem::readFile($sql);
    $queries = preg_split('/;\s+/', $sql);
    $queries = array_filter($queries);

    $conn = $this->getConn(null);

    $charset_info = $this->getCharsetInfo();
    foreach ($charset_info as $key => $value) {
      $charset_info[$key] = qsprintf($conn, '%T', $value);
    }

    foreach ($queries as $query) {
      $query = str_replace('{$NAMESPACE}', $this->namespace, $query);

      foreach ($charset_info as $key => $value) {
        $query = str_replace('{$'.$key.'}', $value, $query);
      }

      try {
        // NOTE: We're using the unsafe "%Z" conversion here. There's no
        // avoiding it since we're executing raw text files full of SQL.
        queryfx($conn, '%Z', $query);
      } catch (AphrontAccessDeniedQueryException $ex) {
        throw new PhutilProxyException(
          pht(
            'Unable to access a required database or table. This almost '.
            'always means that the user you are connecting with ("%s") does '.
            'not have sufficient permissions granted in MySQL. You can '.
            'use `bin/storage databases` to get a list of all databases '.
            'permission is required on.',
            $this->getUser()),
          $ex);
      }
    }
  }

  public function applyPatchPHP($script) {
    $schema_conn = $this->getConn(null);
    require_once $script;
  }

  public function isCharacterSetAvailable($character_set) {
    if ($character_set == 'utf8mb4') {
      if ($this->getDisableUTF8MB4()) {
        return false;
      }
    }

    $conn = $this->getConn(null);
    return self::isCharacterSetAvailableOnConnection($character_set, $conn);
  }

  public function getClientCharset() {
    if ($this->isCharacterSetAvailable('utf8mb4')) {
      return 'utf8mb4';
    } else {
      return 'utf8';
    }
  }

  public static function isCharacterSetAvailableOnConnection(
    $character_set,
    AphrontDatabaseConnection $conn) {
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
      $charset_sort = 'utf8mb4';
      $charset_full = 'utf8mb4';
      $collate_text = 'utf8mb4_bin';
      $collate_sort = 'utf8mb4_unicode_ci';
      $collate_full = 'utf8mb4_unicode_ci';
    } else {
      // If utf8mb4 is not available, we use binary for most data. This allows
      // us to store 4-byte unicode characters.
      //
      // It's possible that strings will be truncated in the middle of a
      // character on insert. We encourage users to set STRICT_ALL_TABLES
      // to prevent this.
      //
      // For "fulltext" and "sort" columns, we don't use binary.
      //
      // With "fulltext", we can not use binary because MySQL won't let us.
      // We use 3-byte utf8 instead and accept being unable to index 4-byte
      // characters.
      //
      // With "sort", if we use binary we lose case insensitivity (for
      // example, "ALincoln@logcabin.com" and "alincoln@logcabin.com" would no
      // longer be identified as the same email address). This can be very
      // confusing and is far worse overall than not supporting 4-byte unicode
      // characters, so we use 3-byte utf8 and accept limited 4-byte support as
      // a tradeoff to get sensible collation behavior. Many columns where
      // collation is important rarely contain 4-byte characters anyway, so we
      // are not giving up too much.

      $charset = 'binary';
      $charset_sort = 'utf8';
      $charset_full = 'utf8';
      $collate_text = 'binary';
      $collate_sort = 'utf8_general_ci';
      $collate_full = 'utf8_general_ci';
    }

    return array(
      self::CHARSET_DEFAULT => $charset,
      self::CHARSET_SORT => $charset_sort,
      self::CHARSET_FULLTEXT => $charset_full,
      self::COLLATE_TEXT => $collate_text,
      self::COLLATE_SORT => $collate_sort,
      self::COLLATE_FULLTEXT => $collate_full,
    );
  }

}
