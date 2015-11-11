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
  protected $editPolicy;
  protected $properties = array();
  protected $isDisabled = 0;
  protected $isDefault = 0;

  private $engine = self::ATTACHABLE;

  public function getTableName() {
    return 'search_editengineconfiguration';
  }

  public static function initializeNewConfiguration(
    PhabricatorUser $actor,
    PhabricatorEditEngine $engine) {

    // TODO: This should probably be controlled by a new defualt capability.
    $edit_policy = PhabricatorPolicies::POLICY_ADMIN;

    return id(new PhabricatorEditEngineConfiguration())
      ->setEngineKey($engine->getEngineKey())
      ->attachEngine($engine)
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
      ->setEditPolicy($edit_policy);
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorEditEngineConfigurationPHIDType::TYPECONST);
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
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_engine' => array(
          'columns' => array('engineKey', 'builtinKey'),
          'unique' => true,
        ),
        'key_default' => array(
          'columns' => array('engineKey', 'isDefault', 'isDisabled'),
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

  public function attachEngine(PhabricatorEditEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->assertAttached($this->engine);
  }

  public function applyConfigurationToFields(
    PhabricatorEditEngine $engine,
    array $fields) {
    $fields = mpull($fields, null, 'getKey');

    $values = $this->getProperty('defaults', array());
    foreach ($fields as $key => $field) {
      if ($engine->getIsCreate()) {
        if (array_key_exists($key, $values)) {
          $field->readDefaultValueFromConfiguration($values[$key]);
        }
      }
    }

    $fields = $this->reorderFields($fields);

    $head_instructions = $this->getProperty('instructions.head');
    if (strlen($head_instructions)) {
      $fields = array(
        'config.instructions.head' => id(new PhabricatorInstructionsEditField())
          ->setKey('config.instructions.head')
          ->setValue($head_instructions),
      ) + $fields;
    }

    return $fields;
  }

  private function reorderFields(array $fields) {
    $keys = array();
    $fields = array_select_keys($fields, $keys) + $fields;

    // Now, move locked fields to the bottom.
    $head = array();
    $tail = array();
    foreach ($fields as $key => $field) {
      if (!$field->getIsLocked()) {
        $head[$key] = $field;
      } else {
        $tail[$key] = $field;
      }
    }

    return $head + $tail;
  }

  public function getURI() {
    $engine_key = $this->getEngineKey();
    $key = $this->getIdentifier();

    return "/transactions/editengine/{$engine_key}/view/{$key}/";
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
