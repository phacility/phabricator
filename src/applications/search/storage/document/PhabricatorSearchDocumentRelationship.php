<?php

final class PhabricatorSearchDocumentRelationship extends PhabricatorSearchDAO {

  protected $relatedPHID;
  protected $relation;
  protected $relatedType;
  protected $relatedTime;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
      self::CONFIG_COLUMN_SCHEMA => array(
        'relation' => 'text4',
        'relatedType' => 'text4',
        'relatedTime' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
        ),
        'relatedPHID' => array(
          'columns' => array('relatedPHID', 'relation'),
        ),
        'relation' => array(
          'columns' => array('relation', 'relatedPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getIDKey() {
    return 'phid';
  }

}
