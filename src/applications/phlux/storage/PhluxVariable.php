<?php

final class PhluxVariable extends PhluxDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorFlaggableInterface,
    PhabricatorPolicyInterface {

  protected $variableKey;
  protected $variableValue;
  protected $viewPolicy;
  protected $editPolicy;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'variableValue' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'variableKey' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_key' => array(
          'columns' => array('variableKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(PhluxVariablePHIDType::TYPECONST);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhluxVariableEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhluxTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->viewPolicy;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->editPolicy;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
