<?php

final class PholioMock extends PholioDAO
  implements
    PhabricatorMarkupInterface,
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorFlaggableInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorProjectInterface {

  const MARKUP_FIELD_DESCRIPTION  = 'markup:description';

  protected $authorPHID;
  protected $viewPolicy;

  protected $name;
  protected $originalName;
  protected $description;
  protected $coverPHID;
  protected $mailKey;
  protected $status;

  private $images = self::ATTACHABLE;
  private $allImages = self::ATTACHABLE;
  private $coverFile = self::ATTACHABLE;
  private $tokenCount = self::ATTACHABLE;

  public static function initializeNewMock(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorApplicationPholio'))
      ->executeOne();

    $view_policy = $app->getPolicy(PholioCapabilityDefaultView::CAPABILITY);

    return id(new PholioMock())
      ->setAuthorPHID($actor->getPHID())
      ->attachImages(array())
      ->setViewPolicy($view_policy);
  }

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

  /**
   * These should be the images currently associated with the Mock.
   */
  public function attachImages(array $images) {
    assert_instances_of($images, 'PholioImage');
    $this->images = $images;
    return $this;
  }

  public function getImages() {
    $this->assertAttached($this->images);
    return $this->images;
  }

  /**
   * These should be *all* images associated with the Mock. This includes
   * images which have been removed and / or replaced from the Mock.
   */
  public function attachAllImages(array $images) {
    assert_instances_of($images, 'PholioImage');
    $this->allImages = $images;
    return $this;
  }

  public function getAllImages() {
    $this->assertAttached($this->images);
    return $this->allImages;
  }

  public function attachCoverFile(PhabricatorFile $file) {
    $this->coverFile = $file;
    return $this;
  }

  public function getCoverFile() {
    $this->assertAttached($this->coverFile);
    return $this->coverFile;
  }

  public function getTokenCount() {
    $this->assertAttached($this->tokenCount);
    return $this->tokenCount;
  }

  public function attachTokenCount($count) {
    $this->tokenCount = $count;
    return $this;
  }

  public function getImageHistorySet($image_id) {
    $images = $this->getAllImages();
    $images = mpull($images, null, 'getID');
    $selected_image = $images[$image_id];

    $replace_map = mpull($images, null, 'getReplacesImagePHID');
    $phid_map = mpull($images, null, 'getPHID');

    // find the earliest image
    $image = $selected_image;
    while (isset($phid_map[$image->getReplacesImagePHID()])) {
      $image = $phid_map[$image->getReplacesImagePHID()];
    }

    // now build history moving forward
    $history = array($image->getID() => $image);
    while (isset($replace_map[$image->getPHID()])) {
      $image = $replace_map[$image->getPHID()];
      $history[$image->getID()] = $image;
    }

    return $history;
  }

  public function getStatuses() {
    $options = array();
    $options['closed'] = 'Closed';
    $options['open'] = 'Open';
    return $options;
  }

  public function isClosed() {
    return ($this->getStatus() == 'closed');
  }


/* -(  PhabricatorSubscribableInterface Implementation  )-------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($this->authorPHID == $phid);
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
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

  public function describeAutomaticCapability($capability) {
    return pht("A mock's owner can always view and edit it.");
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
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PholioTransaction();
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }

}
