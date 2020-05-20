<?php

final class PholioMock extends PholioDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorFlaggableInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorTimelineInterface,
    PhabricatorProjectInterface,
    PhabricatorDestructibleInterface,
    PhabricatorSpacesInterface,
    PhabricatorMentionableInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface {

  const STATUS_OPEN = 'open';
  const STATUS_CLOSED = 'closed';

  protected $authorPHID;
  protected $viewPolicy;
  protected $editPolicy;

  protected $name;
  protected $description;
  protected $coverPHID;
  protected $status;
  protected $spacePHID;

  private $images = self::ATTACHABLE;
  private $coverFile = self::ATTACHABLE;
  private $tokenCount = self::ATTACHABLE;

  public static function initializeNewMock(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorPholioApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(PholioDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(PholioDefaultEditCapability::CAPABILITY);

    return id(new PholioMock())
      ->setAuthorPHID($actor->getPHID())
      ->attachImages(array())
      ->setStatus(self::STATUS_OPEN)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID());
  }

  public function getMonogram() {
    return 'M'.$this->getID();
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'description' => 'text',
        'status' => 'text12',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PholioMockPHIDType::TYPECONST;
  }

  public function attachImages(array $images) {
    assert_instances_of($images, 'PholioImage');
    $images = mpull($images, null, 'getPHID');
    $images = msort($images, 'getSequence');
    $this->images = $images;
    return $this;
  }

  public function getImages() {
    return $this->assertAttached($this->images);
  }

  public function getActiveImages() {
    $images = $this->getImages();

    foreach ($images as $phid => $image) {
      if ($image->getIsObsolete()) {
        unset($images[$phid]);
      }
    }

    return $images;
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
    $images = $this->getImages();
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
    $options[self::STATUS_OPEN] = pht('Open');
    $options[self::STATUS_CLOSED] = pht('Closed');
    return $options;
  }

  public function isClosed() {
    return ($this->getStatus() == 'closed');
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
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }

  public function describeAutomaticCapability($capability) {
    return pht("A mock's owner can always view and edit it.");
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PholioMockEditor();
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


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $images = id(new PholioImageQuery())
        ->setViewer($engine->getViewer())
        ->withMockPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($images as $image) {
        $image->delete();
      }

      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PholioMockFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new PholioMockFerretEngine();
  }


/* -(  PhabricatorTimelineInterace  )---------------------------------------- */


  public function newTimelineEngine() {
    return new PholioMockTimelineEngine();
  }


}
