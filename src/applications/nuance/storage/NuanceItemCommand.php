<?php

final class NuanceItemCommand
  extends NuanceDAO
  implements PhabricatorPolicyInterface {

  protected $itemPHID;
  protected $authorPHID;
  protected $command;
  protected $parameters;

  public static function initializeNewCommand() {
    return new self();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'command' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_item' => array(
          'columns' => array('itemPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
