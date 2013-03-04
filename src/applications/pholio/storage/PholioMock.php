<?php

/**
 * @group pholio
 */
final class PholioMock extends PholioDAO
  implements
    PhabricatorMarkupInterface,
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorApplicationTransactionInterface {

  const MARKUP_FIELD_DESCRIPTION  = 'markup:description';

  protected $authorPHID;
  protected $viewPolicy;

  protected $name;
  protected $originalName;
  protected $description;
  protected $coverPHID;
  protected $mailKey;

  private $images;
  private $coverFile;
  private $tokenCount;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('MOCK');
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function attachImages(array $images) {
    assert_instances_of($images, 'PholioImage');
    $this->images = $images;
    return $this;
  }

  public function getImages() {
    if ($this->images === null) {
      throw new Exception("Call attachImages() before getImages()!");
    }
    return $this->images;
  }

  public function attachCoverFile(PhabricatorFile $file) {
    $this->coverFile = $file;
    return $this;
  }

  public function getCoverFile() {
    if ($this->coverFile === null) {
      throw new Exception("Call attachCoverFile() before getCoverFile()!");
    }
    return $this->coverFile;
  }

  public function getTokenCount() {
    if ($this->tokenCount === null) {
      throw new Exception("Call attachTokenCount() before getTokenCount()!");
    }
    return $this->tokenCount;
  }

  public function attachTokenCount($count) {
    $this->tokenCount = $count;
    return $this;
  }


/* -(  PhabricatorSubscribableInterface Implementation  )-------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($this->authorPHID == $phid);
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
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }


/* -(  PhabricatorMarkupInterface  )----------------------------------------- */


  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    return 'M:'.$hash;
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }

  public function getMarkupText($field) {
    if ($this->getDescription()) {
      $description = $this->getDescription();
    } else {
      $description = pht('No Description Given');
    }

    return $description;
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    require_celerity_resource('phabricator-remarkup-css');
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $output);
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PholioMockEditor();
  }

  public function getApplicationTransactionObject() {
    return new PholioTransaction();
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }

}
