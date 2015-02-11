<?php

final class PhortuneMerchant extends PhortuneDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface {

  protected $name;
  protected $viewPolicy;
  protected $description;

  private $memberPHIDs = self::ATTACHABLE;

  public static function initializeNewMerchant(PhabricatorUser $actor) {
    return id(new PhortuneMerchant())
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
      ->attachMemberPHIDs(array());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'description' => 'text',
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortuneMerchantPHIDType::TYPECONST);
  }

  public function getMemberPHIDs() {
    return $this->assertAttached($this->memberPHIDs);
  }

  public function attachMemberPHIDs(array $member_phids) {
    $this->memberPHIDs = $member_phids;
    return $this;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhortuneMerchantEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhortuneMerchantTransaction();
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
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $members = array_fuse($this->getMemberPHIDs());
    if (isset($members[$viewer->getPHID()])) {
      return true;
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht("A merchant's members an always view and edit it.");
  }

}
