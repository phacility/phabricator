<?php

final class DrydockResource extends DrydockDAO
  implements PhabricatorPolicyInterface {

  protected $id;
  protected $phid;
  protected $blueprintPHID;
  protected $status;

  protected $type;
  protected $name;
  protected $attributes   = array();
  protected $capabilities = array();
  protected $ownerPHID;

  private $blueprint;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'attributes'    => self::SERIALIZATION_JSON,
        'capabilities'  => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'ownerPHID' => 'phid?',
        'status' => 'uint32',
        'type' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(DrydockResourcePHIDType::TYPECONST);
  }

  public function getAttribute($key, $default = null) {
    return idx($this->attributes, $key, $default);
  }

  public function getAttributesForTypeSpec(array $attribute_names) {
    return array_select_keys($this->attributes, $attribute_names);
  }

  public function setAttribute($key, $value) {
    $this->attributes[$key] = $value;
    return $this;
  }

  public function getCapability($key, $default = null) {
    return idx($this->capbilities, $key, $default);
  }

  public function getInterface(DrydockLease $lease, $type) {
    return $this->getBlueprint()->getInterface($this, $lease, $type);
  }

  public function getBlueprint() {
    // TODO: Policy stuff.
    if (empty($this->blueprint)) {
      $blueprint = id(new DrydockBlueprint())
        ->loadOneWhere('phid = %s', $this->blueprintPHID);
      $this->blueprint = $blueprint->getImplementation();
    }
    return $this->blueprint;
  }

  public function closeResource() {
    $this->openTransaction();
      $statuses = array(
        DrydockLeaseStatus::STATUS_PENDING,
        DrydockLeaseStatus::STATUS_ACTIVE,
      );

      $leases = id(new DrydockLeaseQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withResourceIDs(array($this->getID()))
        ->withStatuses($statuses)
        ->execute();

      foreach ($leases as $lease) {
        switch ($lease->getStatus()) {
          case DrydockLeaseStatus::STATUS_PENDING:
            $message = pht('Breaking pending lease (resource closing).');
            $lease->setStatus(DrydockLeaseStatus::STATUS_BROKEN);
            break;
          case DrydockLeaseStatus::STATUS_ACTIVE:
            $message = pht('Releasing active lease (resource closing).');
            $lease->setStatus(DrydockLeaseStatus::STATUS_RELEASED);
            break;
        }
        DrydockBlueprintImplementation::writeLog($this, $lease, $message);
        $lease->save();
      }

      $this->setStatus(DrydockResourceStatus::STATUS_CLOSED);
      $this->save();
    $this->saveTransaction();
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

  public function describeAutomaticCapability($capability) {
    return null;
  }
}
