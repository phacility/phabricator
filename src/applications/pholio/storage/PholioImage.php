<?php

final class PholioImage extends PholioDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface {

  protected $authorPHID;
  protected $mockPHID;
  protected $filePHID;
  protected $name;
  protected $description;
  protected $sequence;
  protected $isObsolete;
  protected $replacesImagePHID = null;

  private $inlineComments = self::ATTACHABLE;
  private $file = self::ATTACHABLE;
  private $mock = self::ATTACHABLE;

  public static function initializeNewImage() {
    return id(new self())
      ->setName('')
      ->setDescription('')
      ->setIsObsolete(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'mockPHID' => 'phid?',
        'name' => 'text128',
        'description' => 'text',
        'sequence' => 'uint32',
        'isObsolete' => 'bool',
        'replacesImagePHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_mock' => array(
          'columns' => array('mockPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PholioImagePHIDType::TYPECONST;
  }

  public function attachFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  public function getFile() {
    return $this->assertAttached($this->file);
  }

  public function attachMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function getMock() {
    return $this->assertAttached($this->mock);
  }

  public function hasMock() {
    return (bool)$this->getMockPHID();
  }

  public function attachInlineComments(array $inline_comments) {
    assert_instances_of($inline_comments, 'PholioTransactionComment');
    $this->inlineComments = $inline_comments;
    return $this;
  }

  public function getInlineComments() {
    $this->assertAttached($this->inlineComments);
    return $this->inlineComments;
  }

  public function getURI() {
    if ($this->hasMock()) {
      $mock = $this->getMock();

      $mock_uri = $mock->getURI();
      $image_id = $this->getID();

      return "{$mock_uri}/{$image_id}/";
    }

    // For now, standalone images have no URI. We could provide one at some
    // point, although it's not clear that there's any motivation to do so.

    return null;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    // If the image is attached to a mock, we use an extended policy to match
    // the mock's permissions.
    if ($this->hasMock()) {
      return PhabricatorPolicies::getMostOpenPolicy();
    }

    // If the image is not attached to a mock, only the author can see it.
    return $this->getAuthorPHID();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    if ($this->hasMock()) {
      return array(
        array(
          $this->getMock(),
          $capability,
        ),
      );
    }

    return array();
  }

}
