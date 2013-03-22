<?php

final class PhabricatorFileImageMacro extends PhabricatorFileDAO
  implements
    PhabricatorSubscribableInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface {

  protected $filePHID;
  protected $phid;
  protected $name;
  protected $isDisabled = 0;

  private $file;

  public function attachFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  public function getFile() {
    if (!$this->file) {
      throw new Exception("Attach a file with attachFile() first!");
    }

    return $this->file;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID  => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_MCRO);
  }

  public function isAutomaticallySubscribed($phid) {
    return false;
  }

  public function getApplicationTransactionEditor() {
    return new PhabricatorMacroEditor();
  }

  public function getApplicationTransactionObject() {
    return new PhabricatorMacroTransaction();
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}

