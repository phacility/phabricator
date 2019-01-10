<?php

final class LegalpadDocument extends LegalpadDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface {

  protected $title;
  protected $contributorCount;
  protected $recentContributorPHIDs = array();
  protected $creatorPHID;
  protected $versions;
  protected $documentBodyPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $mailKey;
  protected $signatureType;
  protected $preamble;
  protected $requireSignature;

  const SIGNATURE_TYPE_NONE        = 'none';
  const SIGNATURE_TYPE_INDIVIDUAL  = 'user';
  const SIGNATURE_TYPE_CORPORATION = 'corp';

  private $documentBody = self::ATTACHABLE;
  private $contributors = self::ATTACHABLE;
  private $signatures = self::ATTACHABLE;
  private $userSignatures = array();

  public static function initializeNewDocument(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorLegalpadApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(LegalpadDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(LegalpadDefaultEditCapability::CAPABILITY);

    return id(new LegalpadDocument())
      ->setVersions(0)
      ->setCreatorPHID($actor->getPHID())
      ->setContributorCount(0)
      ->setRecentContributorPHIDs(array())
      ->attachSignatures(array())
      ->setSignatureType(self::SIGNATURE_TYPE_INDIVIDUAL)
      ->setPreamble('')
      ->setRequireSignature(0)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'recentContributorPHIDs' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'contributorCount' => 'uint32',
        'versions' => 'uint32',
        'mailKey' => 'bytes20',
        'signatureType' => 'text4',
        'preamble' => 'text',
        'requireSignature' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_creator' => array(
          'columns' => array('creatorPHID', 'dateModified'),
        ),
        'key_required' => array(
          'columns' => array('requireSignature', 'dateModified'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorLegalpadDocumentPHIDType::TYPECONST);
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

  public function getSignatures() {
    return $this->assertAttached($this->signatures);
  }

  public function attachSignatures(array $signatures) {
    $this->signatures = $signatures;
    return $this;
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getMonogram() {
    return 'L'.$this->getID();
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  public function getUserSignature($phid) {
    return $this->assertAttachedKey($this->userSignatures, $phid);
  }

  public function attachUserSignature(
    $user_phid,
    LegalpadDocumentSignature $signature = null) {
    $this->userSignatures[$user_phid] = $signature;
    return $this;
  }

  public static function getSignatureTypeMap() {
    return array(
      self::SIGNATURE_TYPE_INDIVIDUAL => pht('Individuals'),
      self::SIGNATURE_TYPE_CORPORATION => pht('Corporations'),
      self::SIGNATURE_TYPE_NONE => pht('No One'),
    );
  }

  public function getSignatureTypeName() {
    $type = $this->getSignatureType();
    return idx(self::getSignatureTypeMap(), $type, $type);
  }

  public function getSignatureTypeIcon() {
    $type = $this->getSignatureType();
    $map = array(
      self::SIGNATURE_TYPE_NONE => '',
      self::SIGNATURE_TYPE_INDIVIDUAL => 'fa-user grey',
      self::SIGNATURE_TYPE_CORPORATION => 'fa-building-o grey',
    );

    return idx($map, $type, 'fa-user grey');
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($this->creatorPHID == $phid);
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
    return pht('The author of a document can always view and edit it.');
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new LegalpadDocumentEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new LegalpadTransaction();
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();

      $bodies = id(new LegalpadDocumentBody())->loadAllWhere(
        'documentPHID = %s',
        $this->getPHID());
      foreach ($bodies as $body) {
        $body->delete();
      }

      $signatures = id(new LegalpadDocumentSignature())->loadAllWhere(
        'documentPHID = %s',
        $this->getPHID());
      foreach ($signatures as $signature) {
        $signature->delete();
      }

    $this->saveTransaction();
  }

}
