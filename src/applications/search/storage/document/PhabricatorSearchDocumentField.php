<?php

final class PhabricatorSearchDocumentField extends PhabricatorSearchDAO {

  protected $phidType;
  protected $field;
  protected $auxPHID;
  protected $corpus;
  protected $stemmedCorpus;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
      self::CONFIG_COLUMN_SCHEMA => array(
        'phidType' => 'text4',
        'field' => 'text4',
        'auxPHID' => 'phid?',
        'corpus' => 'fulltext?',
        'stemmedCorpus' => 'fulltext?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
        ),
        'key_corpus' => array(
          'columns' => array('corpus', 'stemmedCorpus'),
          'type' => 'FULLTEXT',
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getIDKey() {
    return 'phid';
  }

}
