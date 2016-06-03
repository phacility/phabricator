<?php

abstract class PhabricatorSetting extends Phobject {

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  abstract public function getSettingName();

  public function getSettingPanelKey() {
    return null;
  }

  protected function getSettingOrder() {
    return 1000;
  }

  public function getSettingOrderVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getSettingOrder())
      ->addString($this->getSettingName());
  }

  protected function getControlInstructions() {
    return null;
  }

  protected function isEnabledForViewer(PhabricatorUser $viewer) {
    return true;
  }

  public function getSettingDefaultValue() {
    return null;
  }

  final public function getSettingKey() {
    return $this->getPhobjectClassConstant('SETTINGKEY');
  }

  public static function getAllSettings() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getSettingKey')
      ->execute();
  }

  public static function getAllEnabledSettings(PhabricatorUser $viewer) {
    $settings = self::getAllSettings();
    foreach ($settings as $key => $setting) {
      if (!$setting->isEnabledForViewer($viewer)) {
        unset($settings[$key]);
      }
    }
    return $settings;
  }

  final public function newCustomEditFields($object) {
    $fields = array();

    $field = $this->newCustomEditField($object);
    if ($field) {
      $fields[] = $field;
    }

    return $fields;
  }

  protected function newCustomEditField($object) {
    return null;
  }

  protected function newEditField($object, PhabricatorEditField $template) {
    $setting_property = PhabricatorUserPreferencesTransaction::PROPERTY_SETTING;
    $setting_key = $this->getSettingKey();
    $value = $object->getPreference($setting_key);
    $xaction_type = PhabricatorUserPreferencesTransaction::TYPE_SETTING;
    $label = $this->getSettingName();

    $template
      ->setKey($setting_key)
      ->setLabel($label)
      ->setValue($value)
      ->setTransactionType($xaction_type)
      ->setMetadataValue($setting_property, $setting_key);

    $instructions = $this->getControlInstructions();
    if (strlen($instructions)) {
      $template->setControlInstructions($instructions);
    }

    return $template;
  }

  public function validateTransactionValue($value) {
    return;
  }

  public function assertValidValue($value) {
    $this->validateTransactionValue($value);
  }

  public function getTransactionNewValue($value) {
    return $value;
  }

}
