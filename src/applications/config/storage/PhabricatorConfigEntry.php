<?php

final class PhabricatorConfigEntry
  extends PhabricatorConfigEntryDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface {

  protected $namespace;
  protected $configKey;
  protected $value;
  protected $isDeleted;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'value' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'namespace' => 'text64',
        'configKey' => 'text64',
        'isDeleted' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_name' => array(
          'columns' => array('namespace', 'configKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorConfigConfigPHIDType::TYPECONST);
  }

  public static function loadConfigEntry($key) {
    $config_entry = id(new PhabricatorConfigEntry())
      ->loadOneWhere(
        'configKey = %s AND namespace = %s',
        $key,
        'default');

    if (!$config_entry) {
      $config_entry = id(new PhabricatorConfigEntry())
        ->setConfigKey($key)
        ->setNamespace('default')
        ->setIsDeleted(0);
    }

    return $config_entry;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorConfigEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorConfigTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_ADMIN;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
