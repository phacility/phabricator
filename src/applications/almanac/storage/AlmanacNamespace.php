<?php

final class AlmanacNamespace
  extends AlmanacDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorProjectInterface,
    AlmanacPropertyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorNgramsInterface {

  protected $name;
  protected $nameIndex;
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;

  private $almanacProperties = self::ATTACHABLE;

  public static function initializeNewNamespace() {
    return id(new self())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN)
      ->attachAlmanacProperties(array());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'nameIndex' => 'bytes12',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_nameindex' => array(
          'columns' => array('nameIndex'),
          'unique' => true,
        ),
        'key_name' => array(
          'columns' => array('name'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      AlmanacNamespacePHIDType::TYPECONST);
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
    return '/almanac/namespace/view/'.$this->getName().'/';
  }

  public function getNameLength() {
    return strlen($this->getName());
  }

  /**
   * Load the namespace which prevents use of an Almanac name, if one exists.
   */
  public static function loadRestrictedNamespace(
    PhabricatorUser $viewer,
    $name) {

    // For a name like "x.y.z", produce a list of controlling namespaces like
    // ("z", "y.x", "x.y.z").
    $names = array();
    $parts = explode('.', $name);
    for ($ii = 0; $ii < count($parts); $ii++) {
      $names[] = implode('.', array_slice($parts, -($ii + 1)));
    }

    // Load all the possible controlling namespaces.
    $namespaces = id(new AlmanacNamespaceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withNames($names)
      ->execute();
    if (!$namespaces) {
      return null;
    }

    // Find the "nearest" (longest) namespace that exists. If both
    // "sub.domain.com" and "domain.com" exist, we only care about the policy
    // on the former.
    $namespaces = msort($namespaces, 'getNameLength');
    $namespace = last($namespaces);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $namespace,
      PhabricatorPolicyCapability::CAN_EDIT);
    if ($can_edit) {
      return null;
    }

    return $namespace;
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
    return array();
  }

  public function newAlmanacPropertyEditEngine() {
    throw new PhutilMethodNotImplementedException();
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

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new AlmanacNamespaceEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new AlmanacNamespaceTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


  public function newNgrams() {
    return array(
      id(new AlmanacNamespaceNameNgrams())
        ->setValue($this->getName()),
    );
  }

}
