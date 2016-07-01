<?php

final class PhabricatorSearchDocument extends PhabricatorSearchDAO {

  protected $documentType;
  protected $documentTitle;
  protected $documentCreated;
  protected $documentModified;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
      self::CONFIG_COLUMN_SCHEMA => array(
        'documentType' => 'text4',
        'documentTitle' => 'text255',
        'documentCreated' => 'epoch',
        'documentModified' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'PRIMARY' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'documentCreated' => array(
          'columns' => array('documentCreated'),
        ),
        'key_type' => array(
          'columns' => array('documentType', 'documentCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getIDKey() {
    return 'phid';
  }

}
