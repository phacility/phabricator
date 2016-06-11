<?php

final class PhabricatorUserCache extends PhabricatorUserDAO {

  protected $userPHID;
  protected $cacheIndex;
  protected $cacheKey;
  protected $cacheData;
  protected $cacheType;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'cacheIndex' => 'bytes12',
        'cacheKey' => 'text255',
        'cacheData' => 'text',
        'cacheType' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_usercache' => array(
          'columns' => array('userPHID', 'cacheIndex'),
          'unique' => true,
        ),
        'key_cachekey' => array(
          'columns' => array('cacheIndex'),
        ),
        'key_cachetype' => array(
          'columns' => array('cacheType'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function save() {
    $this->cacheIndex = Filesystem::digestForIndex($this->getCacheKey());
    return parent::save();
  }

  public static function writeCache(
    PhabricatorUserCacheType $type,
    $key,
    $user_phid,
    $raw_value) {
    self::writeCaches(
      array(
        array(
          'type' => $type,
          'key' => $key,
          'userPHID' => $user_phid,
          'value' => $raw_value,
        ),
      ));
  }

  public static function writeCaches(array $values) {
    if (PhabricatorEnv::isReadOnly()) {
      return;
    }

    if (!$values) {
      return;
    }

    $table = new self();
    $conn_w = $table->establishConnection('w');

    $sql = array();
    foreach ($values as $value) {
      $key = $value['key'];

      $sql[] = qsprintf(
        $conn_w,
        '(%s, %s, %s, %s, %s)',
        $value['userPHID'],
        PhabricatorHash::digestForIndex($key),
        $key,
        $value['value'],
        $value['type']->getUserCacheType());
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (userPHID, cacheIndex, cacheKey, cacheData, cacheType)
          VALUES %Q
          ON DUPLICATE KEY UPDATE
            cacheData = VALUES(cacheData),
            cacheType = VALUES(cacheType)',
        $table->getTableName(),
        $chunk);
    }

    unset($unguarded);
  }

  public static function clearCache($key, $user_phid) {
    return self::clearCaches($key, array($user_phid));
  }

  public static function clearCaches($key, array $user_phids) {
    if (PhabricatorEnv::isReadOnly()) {
      return;
    }

    if (!$user_phids) {
      return;
    }

    $table = new self();
    $conn_w = $table->establishConnection('w');

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE cacheIndex = %s AND userPHID IN (%Ls)',
      $table->getTableName(),
      PhabricatorHash::digestForIndex($key),
      $user_phids);

    unset($unguarded);
  }

  public static function clearCacheForAllUsers($key) {
    if (PhabricatorEnv::isReadOnly()) {
      return;
    }

    $table = new self();
    $conn_w = $table->establishConnection('w');

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE cacheIndex = %s',
      $table->getTableName(),
      PhabricatorHash::digestForIndex($key));

    unset($unguarded);
  }

}
