<?php

/**
 * @task apps       Building Applications with Custom Fields
 * @task core       Core Properties and Field Identity
 * @task context    Contextual Data
 * @task storage    Field Storage
 * @task appsearch  Integration with ApplicationSearch
 * @task appxaction Integration with ApplicationTransactions
 */
abstract class PhabricatorCustomField {

  private $viewer;
  private $object;

  const ROLE_APPLICATIONTRANSACTIONS  = 'ApplicationTransactions';
  const ROLE_APPLICATIONSEARCH        = 'ApplicationSearch';
  const ROLE_STORAGE                  = 'storage';
  const ROLE_DEFAULT                  = 'default';


/* -(  Building Applications with Custom Fields  )--------------------------- */


  /**
   * @task apps
   */
  public static function raiseUnattachedException(
    PhabricatorCustomFieldInterface $object,
    $role) {
    throw new PhabricatorCustomFieldNotAttachedException(
      "Call attachCustomFields() before getCustomFields()!");
  }


  /**
   * @task apps
   */
  public static function getObjectFields(
    PhabricatorCustomFieldInterface $object,
    $role) {

    try {
      $fields = $object->getCustomFields($role);
    } catch (PhabricatorCustomFieldNotAttachedException $ex) {
      $base_class = $object->getCustomFieldBaseClass();

      $spec = $object->getCustomFieldSpecificationForRole($role);
      if (!is_array($spec)) {
        $obj_class = get_class($object);
        throw new Exception(
          "Expected an array from getCustomFieldSpecificationForRole() for ".
          "object of class '{$obj_class}'.");
      }

      $fields = PhabricatorCustomField::buildFieldList($base_class, $spec);

      foreach ($fields as $key => $field) {
        if (!$field->shouldEnableForRole($role)) {
          unset($fields[$key]);
        }
      }

      foreach ($fields as $field) {
        $field->setObject($object);
      }

      $object->attachCustomFields($role, $fields);
    }

    return $fields;
  }


  /**
   * @task apps
   */
  public static function getObjectField(
    PhabricatorCustomFieldInterface $object,
    $role,
    $field_key) {
    return idx(self::getObjectFields($object, $role), $field_key);
  }


  /**
   * @task apps
   */
  public static function buildFieldList($base_class, array $spec) {
    $field_objects = id(new PhutilSymbolLoader())
      ->setAncestorClass($base_class)
      ->loadObjects();

    $fields = array();
    $from_map = array();
    foreach ($field_objects as $field_object) {
      $current_class = get_class($field_object);
      foreach ($field_object->createFields() as $field) {
        $key = $field->getFieldKey();
        if (isset($fields[$key])) {
          $original_class = $from_map[$key];
          throw new Exception(
            "Both '{$original_class}' and '{$current_class}' define a custom ".
            "field with field key '{$key}'. Field keys must be unique.");
        }
        $from_map[$key] = $current_class;
        $fields[$key] = $field;
      }
    }

    foreach ($fields as $key => $field) {
      if (!$field->isFieldEnabled()) {
        unset($fields[$key]);
      }
    }

    $fields = array_select_keys($fields, array_keys($spec)) + $fields;

    foreach ($spec as $key => $config) {
      if (empty($fields[$key])) {
        continue;
      }
      if (!empty($config['disabled'])) {
        if ($fields[$key]->canDisableField()) {
          unset($fields[$key]);
        }
      }
    }

    return $fields;
  }


/* -(  Core Properties and Field Identity  )--------------------------------- */


  /**
   * Return a key which uniquely identifies this field, like
   * "mycompany:dinosaur:count". Normally you should provide some level of
   * namespacing to prevent collisions.
   *
   * @return string String which uniquely identifies this field.
   * @task core
   */
  abstract public function getFieldKey();


  /**
   * Return a human-readable field name.
   *
   * @return string Human readable field name.
   * @task core
   */
  public function getFieldName() {
    return $this->getFieldKey();
  }


  /**
   * Return a short, human-readable description of the field's behavior. This
   * provides more context to administrators when they are customizing fields.
   *
   * @return string|null Optional human-readable description.
   * @task core
   */
  public function getFieldDescription() {
    return null;
  }


  /**
   * Most field implementations are unique, in that one class corresponds to
   * one field. However, some field implementations are general and a single
   * implementation may drive several fields.
   *
   * For general implementations, the general field implementation can return
   * multiple field instances here.
   *
   * @return list<PhabricatorCustomField> List of fields.
   * @task core
   */
  public function createFields() {
    return array($this);
  }


  /**
   * You can return `false` here if the field should not be enabled for any
   * role. For example, it might depend on something (like an application or
   * library) which isn't installed, or might have some global configuration
   * which allows it to be disabled.
   *
   * @return bool False to completely disable this field for all roles.
   * @task core
   */
  public function isFieldEnabled() {
    return true;
  }


  /**
   * Low level selector for field availability. Fields can appear in different
   * roles (like an edit view, a list view, etc.), but not every field needs
   * to appear everywhere. Fields that are disabled in a role won't appear in
   * that context within applications.
   *
   * Normally, you do not need to override this method. Instead, override the
   * methods specific to roles you want to enable. For example, implement
   * @{method:getStorageKey()} to activate the `'storage'` role.
   *
   * @return bool True to enable the field for the given role.
   * @task core
   */
  public function shouldEnableForRole($role) {
    switch ($role) {
      case self::ROLE_APPLICATIONTRANSACTIONS:
        return $this->shouldAppearInApplicationTransactions();
      case self::ROLE_APPLICATIONSEARCH:
        return $this->shouldAppearInApplicationSearch();
      case self::ROLE_STORAGE:
        return ($this->getStorageKey() !== null);
      case self::ROLE_DEFAULT:
        return true;
      default:
        throw new Exception("Unknown field role '{$role}'!");
    }
  }


  /**
   * Allow administrators to disable this field. Most fields should allow this,
   * but some are fundamental to the behavior of the application and can be
   * locked down to avoid chaos, disorder, and the decline of civilization.
   *
   * @return bool False to prevent this field from being disabled through
   *              configuration.
   * @task core
   */
  public function canDisableField() {
    return true;
  }


  /**
   * Return an index string which uniquely identifies this field.
   *
   * @return string Index string which uniquely identifies this field.
   * @task core
   */
  final public function getFieldIndex() {
    return PhabricatorHash::digestForIndex($this->getFieldKey());
  }


/* -(  Contextual Data  )---------------------------------------------------- */


  /**
   * Sets the object this field belongs to.
   *
   * @param PhabricatorCustomFieldInterface The object this field belongs to.
   * @task context
   */
  final public function setObject(PhabricatorCustomFieldInterface $object) {
    $this->object = $object;
    $this->didSetObject($object);
    return $this;
  }


  /**
   * Get the object this field belongs to.
   *
   * @return PhabricatorCustomFieldInterface The object this field belongs to.
   * @task context
   */
  final public function getObject() {
    return $this->object;
  }


  /**
   * This is a hook, primarily for subclasses to load object data.
   *
   * @return PhabricatorCustomFieldInterface The object this field belongs to.
   * @return void
   */
  protected function didSetObject(PhabricatorCustomFieldInterface $object) {
    return;
  }


  /**
   * @task context
   */
  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }


  /**
   * @task context
   */
  final public function getViewer() {
    return $this->viewer;
  }


  /**
   * @task context
   */
  final protected function requireViewer() {
    if (!$this->viewer) {
      throw new PhabricatorCustomFieldDataNotAvailableException($this);
    }
    return $this->viewer;
  }


/* -(  Storage  )------------------------------------------------------------ */


  /**
   * Return a unique string used to key storage of this field's value, like
   * "mycompany.fieldname" or similar. You can return null (the default) to
   * indicate that this field does not use any storage.
   *
   * Fields which can be edited by the user will most commonly use storage,
   * while some other types of fields (for instance, those which just display
   * information in some stylized way) may not. Many builtin fields do not use
   * storage because their data is available on the object itself.
   *
   * If you implement this, you must also implement @{method:getValueForStorage}
   * and @{method:setValueFromStorage}.
   *
   * In most cases, a reasonable implementation is to simply reuse the field
   * key:
   *
   *   return $this->getFieldKey();
   *
   * @return string|null  Unique key which identifies this field in auxiliary
   *                      field storage. Alternatively, return null (default)
   *                      to indicate that this field does not use storage.
   * @task storage
   */
  public function getStorageKey() {
    return null;
  }


  /**
   * Return a new, empty storage object. This should be a subclass of
   * @{class:PhabricatorCustomFieldStorage} which is bound to the application's
   * database.
   *
   * @return PhabricatorCustomFieldStorage New empty storage object.
   * @task storage
   */
  public function getStorageObject() {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Return a serialized representation of the field value, appropriate for
   * storing in auxiliary field storage. You must implement this method if
   * you implement @{method:getStorageKey}.
   *
   * If the field value is a scalar, it can be returned unmodiifed. If not,
   * it should be serialized (for example, using JSON).
   *
   * @return string Serialized field value.
   * @task storage
   */
  public function getValueForStorage() {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Set the field's value given a serialized storage value. This is called
   * when the field is loaded; if no data is available, the value will be
   * null. You must implement this method if you implement
   * @{method:getStorageKey}.
   *
   * Usually, the value can be loaded directly. If it isn't a scalar, you'll
   * need to undo whatever serialization you applied in
   * @{method:getValueForStorage}.
   *
   * @param string|null Serialized field representation (from
   *                    @{method:getValueForStorage}) or null if no value has
   *                    ever been stored.
   * @return this
   * @task storage
   */
  public function setValueFromStorage($value) {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


/* -(  ApplicationSearch  )-------------------------------------------------- */


  /**
   * Appearing in ApplicationSearch allows a field to be indexed and searched
   * for.
   *
   * @return bool True to appear in ApplicationSearch.
   * @task appsearch
   */
  public function shouldAppearInApplicationSearch() {
    return false;
  }


  /**
   * Return one or more indexes which this field can meaningfully query against
   * to implement ApplicationSearch.
   *
   * Normally, you should build these using @{method:newStringIndex} and
   * @{method:newNumericIndex}. For example, if a field holds a numeric value
   * it might return a single numeric index:
   *
   *   return array($this->newNumericIndex($this->getValue()));
   *
   * If a field holds a more complex value (like a list of users), it might
   * return several string indexes:
   *
   *   $indexes = array();
   *   foreach ($this->getValue() as $phid) {
   *     $indexes[] = $this->newStringIndex($phid);
   *   }
   *   return $indexes;
   *
   * @return list<PhabricatorCustomFieldIndexStorage> List of indexes.
   * @task appsearch
   */
  public function buildFieldIndexes() {
    return array();
  }


  /**
   * Build a new empty storage object for storing string indexes. Normally,
   * this should be a concrete subclass of
   * @{class:PhabricatorCustomFieldStringIndexStorage}.
   *
   * @return PhabricatorCustomFieldStringIndexStorage Storage object.
   * @task appsearch
   */
  protected function newStringIndexStorage() {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Build a new empty storage object for storing string indexes. Normally,
   * this should be a concrete subclass of
   * @{class:PhabricatorCustomFieldStringIndexStorage}.
   *
   * @return PhabricatorCustomFieldStringIndexStorage Storage object.
   * @task appsearch
   */
  protected function newNumericIndexStorage() {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Build and populate storage for a string index.
   *
   * @param string String to index.
   * @return PhabricatorCustomFieldStringIndexStorage Populated storage.
   * @task appsearch
   */
  protected function newStringIndex($value) {
    $key = $this->getFieldIndexKey();
    return $this->newStringIndexStorage()
      ->setIndexKey($key)
      ->setIndexValue($value);
  }


  /**
   * Build and populate storage for a numeric index.
   *
   * @param string Numeric value to index.
   * @return PhabricatorCustomFieldNumericIndexStorage Populated storage.
   * @task appsearch
   */
  protected function newNumericIndex($value) {
    $key = $this->getFieldIndexKey();
    return $this->newNumericIndexStorage()
      ->setIndexKey($key)
      ->setIndexValue($value);
  }


/* -(  ApplicationTransactions  )-------------------------------------------- */


  /**
   * Appearing in ApplicationTrasactions allows a field to be edited using
   * standard workflows.
   *
   * @return bool True to appear in ApplicationTransactions.
   * @task appxaction
   */
  public function shouldAppearInApplicationTransactions() {
    return false;
  }


  /**
   * @task appxaction
   */
  public function getOldValueForApplicationTransactions() {
    return $this->getValueForStorage();
  }


  /**
   * @task appxaction
   */
  public function getNewValueForApplicationTransactions() {
    return $this->getValueForStorage();
  }


  /**
   * @task appxaction
   */
  public function setValueFromApplicationTransactions($value) {
    return $this->setValueFromStorage($value);
  }


  /**
   * @task appxaction
   */
  public function getNewValueFromApplicationTransactions(
    PhabricatorApplicationTransaction $xaction) {
    return $xaction->getNewValue();
  }


  /**
   * @task appxaction
   */
  public function getApplicationTransactionHasEffect(
    PhabricatorApplicationTransaction $xaction) {
    return ($xaction->getOldValue() !== $xaction->getNewValue());
  }


  /**
   * @task appxaction
   */
  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    return;
  }


  /**
   * @task appxaction
   */
  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    if (!$this->shouldEnableForRole(self::ROLE_STORAGE)) {
      return;
    }

    $this->setValueFromApplicationTransaction($xaction->getNewValue());
    $value = $this->getValueForStorage();

    $table = $this->newStorageObject();
    $conn_w = $table->establishConnection('w');

    if ($value === null) {
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE objectPHID = %s AND fieldIndex = %s',
        $this->getObject()->getPHID(),
        $this->getFieldIndex());
    } else {
      queryfx(
        $conn_w,
        'INSERT INTO %T (objectPHID, fieldIndex, fieldValue)
          VALUES (%s, %s, %s)
          ON DUPLICATE KEY UPDATE fieldValue = VALUES(fieldValue)',
        $this->getObject()->getPHID(),
        $this->getFieldIndex(),
        $value);
    }

    return;
  }


/* -(  Edit View  )---------------------------------------------------------- */


  /**
   * @task edit
   */
  public function shouldAppearOnEditView() {
    return false;
  }


  /**
   * @task edit
   */
  public function readValueFromRequest(AphrontRequest $request) {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * @task edit
   */
  public function renderEditControl() {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


}
