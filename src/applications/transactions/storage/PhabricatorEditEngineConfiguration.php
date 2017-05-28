<?php

final class PhabricatorEditEngineConfiguration
  extends PhabricatorSearchDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface {

  protected $engineKey;
  protected $builtinKey;
  protected $name;
  protected $viewPolicy;
  protected $properties = array();
  protected $isDisabled = 0;
  protected $isDefault = 0;
  protected $isEdit = 0;
  protected $createOrder = 0;
  protected $editOrder = 0;
  protected $subtype;

  private $engine = self::ATTACHABLE;

  const LOCK_VISIBLE = 'visible';
  const LOCK_LOCKED = 'locked';
  const LOCK_HIDDEN = 'hidden';

  public function getTableName() {
    return 'search_editengineconfiguration';
  }

  public static function initializeNewConfiguration(
    PhabricatorUser $actor,
    PhabricatorEditEngine $engine) {

    return id(new PhabricatorEditEngineConfiguration())
      ->setSubtype(PhabricatorEditEngine::SUBTYPE_DEFAULT)
      ->setEngineKey($engine->getEngineKey())
      ->attachEngine($engine)
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy());
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorEditEngineConfigurationPHIDType::TYPECONST);
  }

  public function getCreateSortKey() {
    return $this->getSortKey($this->createOrder);
  }

  public function getEditSortKey() {
    return $this->getSortKey($this->editOrder);
  }

  private function getSortKey($order) {
    // Put objects at the bottom by default if they haven't previously been
    // reordered. When they're explicitly reordered, the smallest sort key we
    // assign is 1, so if the object has a value of 0 it means it hasn't been
    // ordered yet.
    if ($order != 0) {
      $group = 'A';
    } else {
      $group = 'B';
    }

    return sprintf(
      "%s%012d%s\0%012d",
      $group,
      $order,
      $this->getName(),
      $this->getID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'engineKey' => 'text64',
        'builtinKey' => 'text64?',
        'name' => 'text255',
        'isDisabled' => 'bool',
        'isDefault' => 'bool',
        'isEdit' => 'bool',
        'createOrder' => 'uint32',
        'editOrder' => 'uint32',
        'subtype' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_engine' => array(
          'columns' => array('engineKey', 'builtinKey'),
          'unique' => true,
        ),
        'key_default' => array(
          'columns' => array('engineKey', 'isDefault', 'isDisabled'),
        ),
        'key_edit' => array(
          'columns' => array('engineKey', 'isEdit', 'isDisabled'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function setBuiltinKey($key) {
    if (strpos($key, '/') !== false) {
      throw new Exception(
        pht('EditEngine BuiltinKey contains an invalid key character "/".'));
    }
    return parent::setBuiltinKey($key);
  }

  public function attachEngine(PhabricatorEditEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->assertAttached($this->engine);
  }

  public function applyConfigurationToFields(
    PhabricatorEditEngine $engine,
    $object,
    array $fields) {
    $fields = mpull($fields, null, 'getKey');

    $is_new = !$object->getID();

    $values = $this->getProperty('defaults', array());
    foreach ($fields as $key => $field) {
      if (!$field->getIsDefaultable()) {
        continue;
      }
      if ($is_new) {
        if (array_key_exists($key, $values)) {
          $field->readDefaultValueFromConfiguration($values[$key]);
        }
      }
    }

    $locks = $this->getFieldLocks();
    foreach ($fields as $field) {
      $key = $field->getKey();
      switch (idx($locks, $key)) {
        case self::LOCK_LOCKED:
          $field->setIsHidden(false);
          if ($field->getIsLockable()) {
            $field->setIsLocked(true);
          }
          break;
        case self::LOCK_HIDDEN:
          $field->setIsHidden(true);
          if ($field->getIsLockable()) {
            $field->setIsLocked(false);
          }
          break;
        case self::LOCK_VISIBLE:
          $field->setIsHidden(false);
          if ($field->getIsLockable()) {
            $field->setIsLocked(false);
          }
          break;
        default:
          // If we don't have an explicit value, don't make any adjustments.
          break;
      }
    }

    $fields = $this->reorderFields($fields);

    $preamble = $this->getPreamble();
    if (strlen($preamble)) {
      $fields = array(
        'config.preamble' => id(new PhabricatorInstructionsEditField())
          ->setKey('config.preamble')
          ->setIsReorderable(false)
          ->setIsDefaultable(false)
          ->setIsLockable(false)
          ->setValue($preamble),
      ) + $fields;
    }

    return $fields;
  }

  private function reorderFields(array $fields) {
    // Fields which can not be reordered are fixed in order at the top of the
    // form. These are used to show instructions or contextual information.

    $fixed = array();
    foreach ($fields as $key => $field) {
      if (!$field->getIsReorderable()) {
        $fixed[$key] = $field;
      }
    }

    $keys = $this->getFieldOrder();

    $fields = $fixed + array_select_keys($fields, $keys) + $fields;

    return $fields;
  }

  public function getURI() {
    $engine_key = $this->getEngineKey();
    $key = $this->getIdentifier();

    return "/transactions/editengine/{$engine_key}/view/{$key}/";
  }

  public function getCreateURI() {
    $form_key = $this->getIdentifier();
    $engine = $this->getEngine();

    try {
      $create_uri = $engine->getEditURI(null, "form/{$form_key}/");
    } catch (Exception $ex) {
      $create_uri = null;
    }

    return $create_uri;
  }

  public function getIdentifier() {
    $key = $this->getID();
    if (!$key) {
      $key = $this->getBuiltinKey();
    }
    return $key;
  }

  public function getDisplayName() {
    $name = $this->getName();
    if (strlen($name)) {
      return $name;
    }

    $builtin = $this->getBuiltinKey();
    if ($builtin !== null) {
      return pht('Builtin Form "%s"', $builtin);
    }

    return pht('Untitled Form');
  }

  public function getPreamble() {
    return $this->getProperty('preamble');
  }

  public function setPreamble($preamble) {
    return $this->setProperty('preamble', $preamble);
  }

  public function setFieldOrder(array $field_order) {
    return $this->setProperty('order', $field_order);
  }

  public function getFieldOrder() {
    return $this->getProperty('order', array());
  }

  public function setFieldLocks(array $field_locks) {
    return $this->setProperty('locks', $field_locks);
  }

  public function getFieldLocks() {
    return $this->getProperty('locks', array());
  }

  public function getFieldDefault($key) {
    $defaults = $this->getProperty('defaults', array());
    return idx($defaults, $key);
  }

  public function setFieldDefault($key, $value) {
    $defaults = $this->getProperty('defaults', array());
    $defaults[$key] = $value;
    return $this->setProperty('defaults', $defaults);
  }

  public function getIcon() {
    return $this->getEngine()->getIcon();
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
        return $this->getEngine()
          ->getApplication()
          ->getPolicy($capability);
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicyFilter::hasCapability(
          $viewer,
          $this->getEngine()->getApplication(),
          PhabricatorPolicyCapability::CAN_EDIT);
    }

    return false;
  }

/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorEditEngineConfigurationEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorEditEngineConfigurationTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }

}
