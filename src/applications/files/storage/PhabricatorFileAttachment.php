<?php

final class PhabricatorFileAttachment
  extends PhabricatorFileDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface {

  protected $objectPHID;
  protected $filePHID;
  protected $attacherPHID;
  protected $attachmentMode;

  private $object = self::ATTACHABLE;
  private $file = self::ATTACHABLE;

  const MODE_ATTACH = 'attach';
  const MODE_REFERENCE = 'reference';
  const MODE_DETACH = 'detach';

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectPHID' => 'phid',
        'filePHID' => 'phid',
        'attacherPHID' => 'phid?',
        'attachmentMode' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID', 'filePHID'),
          'unique' => true,
        ),
        'key_file' => array(
          'columns' => array('filePHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function getModeList() {
    return array(
      self::MODE_ATTACH,
      self::MODE_REFERENCE,
      self::MODE_DETACH,
    );
  }

  public static function getModeNameMap() {
    return array(
      self::MODE_ATTACH => pht('Attached'),
      self::MODE_REFERENCE => pht('Referenced'),
    );
  }

  public function isPolicyAttachment() {
    switch ($this->getAttachmentMode()) {
      case self::MODE_ATTACH:
        return true;
      default:
        return false;
    }
  }

  public function attachObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function attachFile(PhabricatorFile $file = null) {
    $this->file = $file;
    return $this;
  }

  public function getFile() {
    return $this->assertAttached($this->file);
  }

  public function canDetach() {
    switch ($this->getAttachmentMode()) {
      case self::MODE_ATTACH:
        return true;
    }

    return false;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return array(
      array($this->getObject(), $capability),
    );
  }

}
