<?php

final class DrydockAuthorization extends DrydockDAO
  implements
    PhabricatorPolicyInterface {

  const OBJECTAUTH_ACTIVE = 'active';
  const OBJECTAUTH_INACTIVE = 'inactive';

  const BLUEPRINTAUTH_REQUESTED = 'requested';
  const BLUEPRINTAUTH_AUTHORIZED = 'authorized';
  const BLUEPRINTAUTH_DECLINED = 'declined';

  protected $blueprintPHID;
  protected $blueprintAuthorizationState;
  protected $objectPHID;
  protected $objectAuthorizationState;

  private $blueprint = self::ATTACHABLE;
  private $object = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'blueprintAuthorizationState' => 'text32',
        'objectAuthorizationState' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_unique' => array(
          'columns' => array('objectPHID', 'blueprintPHID'),
          'unique' => true,
        ),
        'key_blueprint' => array(
          'columns' => array('blueprintPHID', 'blueprintAuthorizationState'),
        ),
        'key_object' => array(
          'columns' => array('objectPHID', 'objectAuthorizationState'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      DrydockAuthorizationPHIDType::TYPECONST);
  }

  public function attachBlueprint(DrydockBlueprint $blueprint) {
    $this->blueprint = $blueprint;
    return $this;
  }

  public function getBlueprint() {
    return $this->assertAttached($this->blueprint);
  }

  public function attachObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public static function getBlueprintStateIcon($state) {
    $map = array(
      self::BLUEPRINTAUTH_REQUESTED => 'fa-exclamation-circle indigo',
      self::BLUEPRINTAUTH_AUTHORIZED => 'fa-check-circle green',
      self::BLUEPRINTAUTH_DECLINED => 'fa-times red',
    );

    return idx($map, $state, null);
  }

  public static function getBlueprintStateName($state) {
    $map = array(
      self::BLUEPRINTAUTH_REQUESTED => pht('Requested'),
      self::BLUEPRINTAUTH_AUTHORIZED => pht('Authorized'),
      self::BLUEPRINTAUTH_DECLINED => pht('Declined'),
    );

    return idx($map, $state, pht('<Unknown: %s>', $state));
  }

  public static function getObjectStateName($state) {
    $map = array(
      self::OBJECTAUTH_ACTIVE => pht('Active'),
      self::OBJECTAUTH_INACTIVE => pht('Inactive'),
    );

    return idx($map, $state, pht('<Unknown: %s>', $state));
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getBlueprint()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBlueprint()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'An authorization inherits the policies of the blueprint it '.
      'authorizes access to.');
  }


}
