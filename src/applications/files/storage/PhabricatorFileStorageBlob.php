<?php

/**
 * Simple blob store DAO for @{class:PhabricatorMySQLFileStorageEngine}.
 */
final class PhabricatorFileStorageBlob extends PhabricatorFileDAO {

  protected $data;

  public function getConfiguration() {
    return array(
      self::CONFIG_BINARY => array(
        'data' => true,
      ),
    ) + parent::getConfiguration();
  }

}
