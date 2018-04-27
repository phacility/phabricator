<?php

final class PhabricatorMetaMTAMailProperties
  extends PhabricatorMetaMTADAO
  implements PhabricatorPolicyInterface {

  protected $objectPHID;
  protected $mailProperties = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'mailProperties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getMailProperty($key, $default = null) {
    return idx($this->mailProperties, $key, $default);
  }

  public function setMailProperty($key, $value) {
    $this->mailProperties[$key] = $value;
    return $this;
  }

  public static function loadMailKey($object) {
    // If this is an older object with an onboard "mailKey" property, just
    // use it.
    // TODO: We should eventually get rid of these and get rid of this
    // piece of code.
    if ($object->hasProperty('mailKey')) {
      return $object->getMailKey();
    }

    $viewer = PhabricatorUser::getOmnipotentUser();
    $object_phid = $object->getPHID();

    $properties = id(new PhabricatorMetaMTAMailPropertiesQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object_phid))
      ->executeOne();
    if (!$properties) {
      $properties = id(new self())
        ->setObjectPHID($object_phid);
    }

    $mail_key = $properties->getMailProperty('mailKey');
    if ($mail_key !== null) {
      return $mail_key;
    }

    $mail_key = Filesystem::readRandomCharacters(20);
    $properties->setMailProperty('mailKey', $mail_key);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $properties->save();
    unset($unguarded);

    return $mail_key;
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
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
