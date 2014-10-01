<?php

final class PhabricatorSearchDocumentField extends PhabricatorSearchDAO {

  protected $phidType;
  protected $field;
  protected $auxPHID;
  protected $corpus;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
      self::CONFIG_COLUMN_SCHEMA => array(
        'phidType' => 'text4',
        'field' => 'text4',
        'auxPHID' => 'phid?',
        'corpus' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
        ),

        // NOTE: This is a fulltext index! Be careful!
        'corpus' => array(
          'columns' => array('corpus'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getIDKey() {
    return 'phid';
  }

}
