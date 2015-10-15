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

  /**
   * Apply external authorization effects after a user chagnes the value of a
   * blueprint selector control an object.
   *
   * @param PhabricatorUser User applying the change.
   * @param phid Object PHID change is being applied to.
   * @param list<phid> Old blueprint PHIDs.
   * @param list<phid> New blueprint PHIDs.
   * @return void
   */
  public static function applyAuthorizationChanges(
    PhabricatorUser $viewer,
    $object_phid,
    array $old,
    array $new) {

    $old_phids = array_fuse($old);
    $new_phids = array_fuse($new);

    $rem_phids = array_diff_key($old_phids, $new_phids);
    $add_phids = array_diff_key($new_phids, $old_phids);

    $altered_phids = $rem_phids + $add_phids;

    if (!$altered_phids) {
      return;
    }

    $authorizations = id(new DrydockAuthorizationQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withObjectPHIDs(array($object_phid))
      ->withBlueprintPHIDs($altered_phids)
      ->execute();
    $authorizations = mpull($authorizations, null, 'getBlueprintPHID');

    $state_active = self::OBJECTAUTH_ACTIVE;
    $state_inactive = self::OBJECTAUTH_INACTIVE;

    $state_requested = self::BLUEPRINTAUTH_REQUESTED;

    // Disable the object side of the authorization for any existing
    // authorizations.
    foreach ($rem_phids as $rem_phid) {
      $authorization = idx($authorizations, $rem_phid);
      if (!$authorization) {
        continue;
      }

      $authorization
        ->setObjectAuthorizationState($state_inactive)
        ->save();
    }

    // For new authorizations, either add them or reactivate them depending
    // on the current state.
    foreach ($add_phids as $add_phid) {
      $needs_update = false;

      $authorization = idx($authorizations, $add_phid);
      if (!$authorization) {
        $authorization = id(new DrydockAuthorization())
          ->setObjectPHID($object_phid)
          ->setObjectAuthorizationState($state_active)
          ->setBlueprintPHID($add_phid)
          ->setBlueprintAuthorizationState($state_requested);

        $needs_update = true;
      } else {
        $current_state = $authorization->getObjectAuthorizationState();
        if ($current_state != $state_active) {
          $authorization->setObjectAuthorizationState($state_active);
          $needs_update = true;
        }
      }

      if ($needs_update) {
        $authorization->save();
      }
    }
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
