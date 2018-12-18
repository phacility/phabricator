<?php

final class PholioImage extends PholioDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface {

  protected $authorPHID;
  protected $mockID;
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
        'mockID' => 'id?',
        'name' => 'text128',
        'description' => 'text',
        'sequence' => 'uint32',
        'isObsolete' => 'bool',
        'replacesImagePHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'keyPHID' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'mockID' => array(
          'columns' => array('mockID', 'isObsolete', 'sequence'),
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

  public function attachInlineComments(array $inline_comments) {
    assert_instances_of($inline_comments, 'PholioTransactionComment');
    $this->inlineComments = $inline_comments;
    return $this;
  }

  public function getInlineComments() {
    $this->assertAttached($this->inlineComments);
    return $this->inlineComments;
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
    if ($this->getMockID()) {
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
    if ($this->getMockID()) {
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
