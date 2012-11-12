<?php

/**
 * @group project
 */
final class PhabricatorProjectTransaction extends PhabricatorProjectDAO {

  protected $projectID;
  protected $authorPHID;
  protected $transactionType;
  protected $oldValue;
  protected $newValue;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'oldValue' => self::SERIALIZATION_JSON,
        'newValue' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

}
