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

    if (PhabricatorEnv::isReadOnly()) {
      return;
    }

    $table = new self();
    $conn_w = $table->establishConnection('w');

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    queryfx(
      $conn_w,
      'INSERT INTO %T (userPHID, cacheIndex, cacheKey, cacheData, cacheType)
        VALUES (%s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE cacheData = VALUES(cacheData)',
      $table->getTableName(),
      $user_phid,
      PhabricatorHash::digestForIndex($key),
      $key,
      $raw_value,
      $type->getUserCacheType());

    unset($unguarded);
  }

  public static function clearCache($key, $user_phid) {
    if (PhabricatorEnv::isReadOnly()) {
      return;
    }

    $table = new self();
    $conn_w = $table->establishConnection('w');

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE cacheIndex = %s AND userPHID = %s',
      $table->getTableName(),
      PhabricatorHash::digestForIndex($key),
      $user_phid);

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
