<?php

final class PholioImage extends PholioDAO
  implements
    PhabricatorMarkupInterface,
    PhabricatorPolicyInterface {

  const MARKUP_FIELD_DESCRIPTION  = 'markup:description';

  protected $mockID;
  protected $filePHID;
  protected $name = '';
  protected $description = '';
  protected $sequence;
  protected $isObsolete = 0;
  protected $replacesImagePHID = null;

  private $inlineComments = self::ATTACHABLE;
  private $file = self::ATTACHABLE;
  private $mock = self::ATTACHABLE;

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

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(PholioImagePHIDType::TYPECONST);
  }

  public function attachFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  public function getFile() {
    $this->assertAttached($this->file);
    return $this->file;
  }

  public function attachMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function getMock() {
    $this->assertAttached($this->mock);
    return $this->mock;
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


/* -(  PhabricatorMarkupInterface  )----------------------------------------- */


  public function getMarkupFieldKey($field) {
    $content = $this->getMarkupText($field);
    return PhabricatorMarkupEngine::digestRemarkupContent($this, $content);
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }

  public function getMarkupText($field) {
    return $this->getDescription();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */

  public function getCapabilities() {
    return $this->getMock()->getCapabilities();
  }

  public function getPolicy($capability) {
    return $this->getMock()->getPolicy($capability);
  }

  // really the *mock* controls who can see an image
  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getMock()->hasAutomaticCapability($capability, $viewer);
  }

}
