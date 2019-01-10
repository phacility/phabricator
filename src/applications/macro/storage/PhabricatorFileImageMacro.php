<?php

final class PhabricatorFileImageMacro extends PhabricatorFileDAO
  implements
    PhabricatorSubscribableInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorFlaggableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorPolicyInterface {

  protected $authorPHID;
  protected $filePHID;
  protected $name;
  protected $isDisabled = 0;
  protected $audioPHID;
  protected $audioBehavior = self::AUDIO_BEHAVIOR_NONE;
  protected $mailKey;

  private $file = self::ATTACHABLE;
  private $audio = self::ATTACHABLE;

  const AUDIO_BEHAVIOR_NONE   = 'audio:none';
  const AUDIO_BEHAVIOR_ONCE   = 'audio:once';
  const AUDIO_BEHAVIOR_LOOP   = 'audio:loop';

  public function attachFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  public function getFile() {
    return $this->assertAttached($this->file);
  }

  public function attachAudio(PhabricatorFile $audio = null) {
    $this->audio = $audio;
    return $this;
  }

  public function getAudio() {
    return $this->assertAttached($this->audio);
  }

  public static function initializeNewFileImageMacro(PhabricatorUser $actor) {
    $macro = id(new self())
      ->setAuthorPHID($actor->getPHID());
    return $macro;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID  => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'authorPHID' => 'phid?',
        'isDisabled' => 'bool',
        'audioPHID' => 'phid?',
        'audioBehavior' => 'text64',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'name' => array(
          'columns' => array('name'),
          'unique' => true,
        ),
        'key_disabled' => array(
          'columns' => array('isDisabled'),
        ),
        'key_dateCreated' => array(
          'columns' => array('dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorMacroMacroPHIDType::TYPECONST);
  }


  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getViewURI() {
    return '/macro/view/'.$this->getID().'/';
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorMacroEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorMacroTransaction();
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }


/* -(  PhabricatorTokenRecevierInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
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
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        $app = PhabricatorApplication::getByClass(
          'PhabricatorMacroApplication');
        return $app->getPolicy(PhabricatorMacroManageCapability::CAPABILITY);
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
