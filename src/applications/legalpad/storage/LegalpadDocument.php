<?php

/**
 * @group legalpad
 */
final class LegalpadDocument extends LegalpadDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorApplicationTransactionInterface {

  protected $phid;
  protected $title;
  protected $contributorCount;
  protected $recentContributorPHIDs = array();
  protected $creatorPHID;
  protected $versions;
  protected $documentBodyPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $mailKey;

  private $documentBody = self::ATTACHABLE;
  private $contributors = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'recentContributorPHIDs' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorLegalpadPHIDTypeDocument::TYPECONST);
  }

  public function getDocumentBody() {
    return $this->assertAttached($this->documentBody);
  }

  public function attachDocumentBody(LegalpadDocumentBody $body) {
    $this->documentBody = $body;
    return $this;
  }

  public function getContributors() {
    return $this->assertAttached($this->contributors);
  }

  public function attachContributors(array $contributors) {
    $this->contributors = $contributors;
    return $this;
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

/* -(  PhabricatorSubscribableInterface Implementation  )-------------------- */

  public function isAutomaticallySubscribed($phid) {
    return ($this->creatorPHID == $phid);
  }

/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $policy = $this->viewPolicy;
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        $policy = $this->editPolicy;
        break;
      default:
        $policy = PhabricatorPolicies::POLICY_NOONE;
        break;
    }
    return $policy;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return ($user->getPHID() == $this->getCreatorPHID());
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'The author of a document can always view and edit it.');
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */

  public function getApplicationTransactionEditor() {
    return new LegalpadDocumentEditor();
  }

  public function getApplicationTransactionObject() {
    return new LegalpadTransaction();
  }

}
