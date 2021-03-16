<?php

final class AlmanacNamespace
  extends AlmanacDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorProjectInterface,
    PhabricatorDestructibleInterface,
    PhabricatorNgramsInterface,
    PhabricatorConduitResultInterface {

  protected $name;
  protected $nameIndex;
  protected $viewPolicy;
  protected $editPolicy;

  public static function initializeNewNamespace() {
    return id(new self())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'nameIndex' => 'bytes12',
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

  public function getPHIDType() {
    return AlmanacNamespacePHIDType::TYPECONST;
  }

  public function save() {
    AlmanacNames::validateName($this->getName());

    $this->nameIndex = PhabricatorHash::digestForIndex($this->getName());

    return parent::save();
  }

  public function getURI() {
    return urisprintf(
      '/almanac/namespace/view/%s/',
      $this->getName());
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


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new AlmanacNamespaceEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new AlmanacNamespaceTransaction();
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


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the namespace.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }


}
