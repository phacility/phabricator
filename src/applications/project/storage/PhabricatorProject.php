<?php

final class PhabricatorProject extends PhabricatorProjectDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $phid;
  protected $status = PhabricatorProjectStatus::STATUS_ACTIVE;
  protected $authorPHID;
  protected $subprojectPHIDs = array();
  protected $phrictionSlug;

  protected $viewPolicy;
  protected $editPolicy;
  protected $joinPolicy;

  private $subprojectsNeedUpdate;
  private $memberPHIDs;
  private $sparseMembers = array();

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
      PhabricatorPolicyCapability::CAN_JOIN,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
      case PhabricatorPolicyCapability::CAN_JOIN:
        return $this->getJoinPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if ($this->isUserMember($viewer->getPHID())) {
          // Project members can always view a project.
          return true;
        }
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        break;
      case PhabricatorPolicyCapability::CAN_JOIN:
        $can_edit = PhabricatorPolicyCapability::CAN_EDIT;
        if (PhabricatorPolicyFilter::hasCapability($viewer, $this, $can_edit)) {
          // Project editors can always join a project.
          return true;
        }
        break;
    }

    return false;
  }

  public function isUserMember($user_phid) {
    if (!isset($this->sparseMembers[$user_phid])) {
      throw new Exception(
        "Call setIsUserMember() before isUserMember()!");
    }
    return $this->sparseMembers[$user_phid];
  }

  public function setIsUserMember($user_phid, $is_member) {
    $this->sparseMembers[$user_phid] = $is_member;
    return $this;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'subprojectPHIDs' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_PROJ);
  }

  public function loadProfile() {
    $profile = id(new PhabricatorProjectProfile())->loadOneWhere(
      'projectPHID = %s',
      $this->getPHID());
    return $profile;
  }

  public function attachMemberPHIDs(array $phids) {
    $this->memberPHIDs = $phids;
    return $this;
  }

  public function getMemberPHIDs() {
    if ($this->memberPHIDs === null) {
      throw new Exception("Call attachMemberPHIDs() first!");
    }
    return $this->memberPHIDs;
  }

  public function loadMemberPHIDs() {
    if (!$this->getPHID()) {
      return array();
    }
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getPHID(),
      PhabricatorEdgeConfig::TYPE_PROJ_MEMBER);
  }

  public function setPhrictionSlug($slug) {

    // NOTE: We're doing a little magic here and stripping out '/' so that
    // project pages always appear at top level under projects/ even if the
    // display name is "Hack / Slash" or similar (it will become
    // 'hack_slash' instead of 'hack/slash').

    $slug = str_replace('/', ' ', $slug);
    $slug = PhabricatorSlug::normalize($slug);
    $this->phrictionSlug = $slug;
    return $this;
  }

}
