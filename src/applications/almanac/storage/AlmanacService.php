<?php

final class AlmanacService
  extends AlmanacDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorProjectInterface,
    AlmanacPropertyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorNgramsInterface,
    PhabricatorConduitResultInterface,
    PhabricatorExtendedPolicyInterface {

  protected $name;
  protected $nameIndex;
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;
  protected $serviceType;

  private $almanacProperties = self::ATTACHABLE;
  private $bindings = self::ATTACHABLE;
  private $serviceImplementation = self::ATTACHABLE;

  public static function initializeNewService($type) {
    $type_map = AlmanacServiceType::getAllServiceTypes();

    $implementation = idx($type_map, $type);
    if (!$implementation) {
      throw new Exception(
        pht(
          'No Almanac service type "%s" exists!',
          $type));
    }

    return id(new AlmanacService())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN)
      ->attachAlmanacProperties(array())
      ->setServiceType($type)
      ->attachServiceImplementation($implementation);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'nameIndex' => 'bytes12',
        'mailKey' => 'bytes20',
        'serviceType' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_name' => array(
          'columns' => array('nameIndex'),
          'unique' => true,
        ),
        'key_nametext' => array(
          'columns' => array('name'),
        ),
        'key_servicetype' => array(
          'columns' => array('serviceType'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(AlmanacServicePHIDType::TYPECONST);
  }

  public function save() {
    AlmanacNames::validateName($this->getName());

    $this->nameIndex = PhabricatorHash::digestForIndex($this->getName());

    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }

    return parent::save();
  }

  public function getURI() {
    return '/almanac/service/view/'.$this->getName().'/';
  }

  public function getBindings() {
    return $this->assertAttached($this->bindings);
  }

  public function getActiveBindings() {
    $bindings = $this->getBindings();

    // Filter out disabled bindings.
    foreach ($bindings as $key => $binding) {
      if ($binding->getIsDisabled()) {
        unset($bindings[$key]);
      }
    }

    return $bindings;
  }

  public function attachBindings(array $bindings) {
    $this->bindings = $bindings;
    return $this;
  }

  public function getServiceImplementation() {
    return $this->assertAttached($this->serviceImplementation);
  }

  public function attachServiceImplementation(AlmanacServiceType $type) {
    $this->serviceImplementation = $type;
    return $this;
  }

  public function isClusterService() {
    return $this->getServiceImplementation()->isClusterServiceType();
  }


/* -(  AlmanacPropertyInterface  )------------------------------------------- */


  public function attachAlmanacProperties(array $properties) {
    assert_instances_of($properties, 'AlmanacProperty');
    $this->almanacProperties = mpull($properties, null, 'getFieldName');
    return $this;
  }

  public function getAlmanacProperties() {
    return $this->assertAttached($this->almanacProperties);
  }

  public function hasAlmanacProperty($key) {
    $this->assertAttached($this->almanacProperties);
    return isset($this->almanacProperties[$key]);
  }

  public function getAlmanacProperty($key) {
    return $this->assertAttachedKey($this->almanacProperties, $key);
  }

  public function getAlmanacPropertyValue($key, $default = null) {
    if ($this->hasAlmanacProperty($key)) {
      return $this->getAlmanacProperty($key)->getFieldValue();
    } else {
      return $default;
    }
  }

  public function getAlmanacPropertyFieldSpecifications() {
    return $this->getServiceImplementation()->getFieldSpecifications();
  }

  public function newAlmanacPropertyEditEngine() {
    return new AlmanacServicePropertyEditEngine();
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
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->isClusterService()) {
          return array(
            array(
              new PhabricatorAlmanacApplication(),
              AlmanacManageClusterServicesCapability::CAPABILITY,
            ),
          );
        }
        break;
    }

    return array();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new AlmanacServiceEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new AlmanacServiceTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $bindings = id(new AlmanacBindingQuery())
      ->setViewer($engine->getViewer())
      ->withServicePHIDs(array($this->getPHID()))
      ->execute();
    foreach ($bindings as $binding) {
      $engine->destroyObject($binding);
    }

    $this->delete();
  }


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


  public function newNgrams() {
    return array(
      id(new AlmanacServiceNameNgrams())
        ->setValue($this->getName()),
    );
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the service.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('serviceType')
        ->setType('string')
        ->setDescription(pht('The service type constant.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
      'serviceType' => $this->getServiceType(),
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new AlmanacPropertiesSearchEngineAttachment())
        ->setAttachmentKey('properties'),
      id(new AlmanacBindingsSearchEngineAttachment())
        ->setAttachmentKey('bindings'),
    );
  }

}
