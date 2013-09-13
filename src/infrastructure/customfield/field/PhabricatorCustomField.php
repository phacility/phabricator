<?php

/**
 * @task apps       Building Applications with Custom Fields
 * @task core       Core Properties and Field Identity
 * @task proxy      Field Proxies
 * @task context    Contextual Data
 * @task storage    Field Storage
 * @task appsearch  Integration with ApplicationSearch
 * @task appxaction Integration with ApplicationTransactions
 * @task edit       Integration with edit views
 * @task view       Integration with property views
 * @task list       Integration with list views
 */
abstract class PhabricatorCustomField {

  private $viewer;
  private $object;
  private $proxy;

  const ROLE_APPLICATIONTRANSACTIONS  = 'ApplicationTransactions';
  const ROLE_APPLICATIONSEARCH        = 'ApplicationSearch';
  const ROLE_STORAGE                  = 'storage';
  const ROLE_DEFAULT                  = 'default';
  const ROLE_EDIT                     = 'edit';
  const ROLE_VIEW                     = 'view';
  const ROLE_LIST                     = 'list';


/* -(  Building Applications with Custom Fields  )--------------------------- */


  /**
   * @task apps
   */
  public static function getObjectFields(
    PhabricatorCustomFieldInterface $object,
    $role) {

    try {
      $attachment = $object->getCustomFields();
    } catch (PhabricatorDataNotAttachedException $ex) {
      $attachment = new PhabricatorCustomFieldAttachment();
      $object->attachCustomFields($attachment);
    }

    try {
      $field_list = $attachment->getCustomFieldList($role);
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

      $field_list = new PhabricatorCustomFieldList($fields);
      $attachment->addCustomFieldList($role, $field_list);
    }

    return $field_list;
  }


  /**
   * @task apps
   */
  public static function getObjectField(
    PhabricatorCustomFieldInterface $object,
    $role,
    $field_key) {

    $fields = self::getObjectFields($object, $role)->getFields();

    return idx($fields, $field_key);
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
  public function getFieldKey() {
    if ($this->proxy) {
      return $this->proxy->getFieldKey();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException(
      $this,
      $field_key_is_incomplete = true);
  }


  /**
   * Return a human-readable field name.
   *
   * @return string Human readable field name.
   * @task core
   */
  public function getFieldName() {
    if ($this->proxy) {
      return $this->proxy->getFieldName();
    }
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
    if ($this->proxy) {
      return $this->proxy->getFieldDescription();
    }
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
    if ($this->proxy) {
      return $this->proxy->isFieldEnabled();
    }
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
   * @{method:shouldUseStorage()} to activate the `'storage'` role.
   *
   * @return bool True to enable the field for the given role.
   * @task core
   */
  public function shouldEnableForRole($role) {
    if ($this->proxy) {
      return $this->proxy->shouldEnableForRole($role);
    }

    switch ($role) {
      case self::ROLE_APPLICATIONTRANSACTIONS:
        return $this->shouldAppearInApplicationTransactions();
      case self::ROLE_APPLICATIONSEARCH:
        return $this->shouldAppearInApplicationSearch();
      case self::ROLE_STORAGE:
        return $this->shouldUseStorage();
      case self::ROLE_EDIT:
        return $this->shouldAppearInEditView();
      case self::ROLE_VIEW:
        return $this->shouldAppearInPropertyView();
      case self::ROLE_LIST:
        return $this->shouldAppearInListView();
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


/* -(  Field Proxies  )------------------------------------------------------ */


  /**
   * Proxies allow a field to use some other field's implementation for most
   * of their behavior while still subclassing an application field. When a
   * proxy is set for a field with @{method:setProxy}, all of its methods will
   * call through to the proxy by default.
   *
   * This is most commonly used to implement configuration-driven custom fields
   * using @{class:PhabricatorStandardCustomField}.
   *
   * This method must be overridden to return `true` before a field can accept
   * proxies.
   *
   * @return bool True if you can @{method:setProxy} this field.
   * @task proxy
   */
  public function canSetProxy() {
    if ($this instanceof PhabricatorStandardCustomFieldInterface) {
      return true;
    }
    return false;
  }


  /**
   * Set the proxy implementation for this field. See @{method:canSetProxy} for
   * discussion of field proxies.
   *
   * @param PhabricatorCustomField Field implementation.
   * @return this
   */
  final public function setProxy(PhabricatorCustomField $proxy) {
    if (!$this->canSetProxy()) {
      throw new PhabricatorCustomFieldNotProxyException($this);
    }

    $this->proxy = $proxy;
    return $this;
  }


  /**
   * Get the field's proxy implementation, if any. For discussion, see
   * @{method:canSetProxy}.
   *
   * @return PhabricatorCustomField|null  Proxy field, if one is set.
   */
  final public function getProxy() {
    return $this->proxy;
  }


/* -(  Contextual Data  )---------------------------------------------------- */


  /**
   * Sets the object this field belongs to.
   *
   * @param PhabricatorCustomFieldInterface The object this field belongs to.
   * @task context
   */
  final public function setObject(PhabricatorCustomFieldInterface $object) {
    if ($this->proxy) {
      $this->proxy->setObject($object);
      return $this;
    }

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
    if ($this->proxy) {
      return $this->proxy->getObject();
    }

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
    if ($this->proxy) {
      $this->proxy->setViewer($viewer);
      return $this;
    }

    $this->viewer = $viewer;
    return $this;
  }


  /**
   * @task context
   */
  final public function getViewer() {
    if ($this->proxy) {
      return $this->proxy->getViewer();
    }

    return $this->viewer;
  }


  /**
   * @task context
   */
  final protected function requireViewer() {
    if ($this->proxy) {
      return $this->proxy->requireViewer();
    }

    if (!$this->viewer) {
      throw new PhabricatorCustomFieldDataNotAvailableException($this);
    }
    return $this->viewer;
  }


/* -(  Storage  )------------------------------------------------------------ */


  /**
   * Return true to use field storage.
   *
   * Fields which can be edited by the user will most commonly use storage,
   * while some other types of fields (for instance, those which just display
   * information in some stylized way) may not. Many builtin fields do not use
   * storage because their data is available on the object itself.
   *
   * If you implement this, you must also implement @{method:getValueForStorage}
   * and @{method:setValueFromStorage}.
   *
   * @return bool True to use storage.
   * @task storage
   */
  public function shouldUseStorage() {
    if ($this->proxy) {
      return $this->proxy->shouldUseStorage();
    }
    return false;
  }


  /**
   * Return a new, empty storage object. This should be a subclass of
   * @{class:PhabricatorCustomFieldStorage} which is bound to the application's
   * database.
   *
   * @return PhabricatorCustomFieldStorage New empty storage object.
   * @task storage
   */
  public function newStorageObject() {
    if ($this->proxy) {
      return $this->proxy->newStorageObject();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Return a serialized representation of the field value, appropriate for
   * storing in auxiliary field storage. You must implement this method if
   * you implement @{method:shouldUseStorage}.
   *
   * If the field value is a scalar, it can be returned unmodiifed. If not,
   * it should be serialized (for example, using JSON).
   *
   * @return string Serialized field value.
   * @task storage
   */
  public function getValueForStorage() {
    if ($this->proxy) {
      return $this->proxy->getValueForStorage();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Set the field's value given a serialized storage value. This is called
   * when the field is loaded; if no data is available, the value will be
   * null. You must implement this method if you implement
   * @{method:shouldUseStorage}.
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
    if ($this->proxy) {
      return $this->proxy->setValueFromStorage($value);
    }
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
    if ($this->proxy) {
      return $this->proxy->shouldAppearInApplicationSearch();
    }
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
    if ($this->proxy) {
      return $this->proxy->buildFieldIndexes();
    }
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
    if ($this->proxy) {
      return $this->proxy->newStringIndexStorage();
    }
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
    if ($this->proxy) {
      return $this->proxy->newStringIndexStorage();
    }
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
    if ($this->proxy) {
      return $this->proxy->newStringIndex();
    }

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
    if ($this->proxy) {
      return $this->proxy->newNumericIndex();
    }
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
    if ($this->proxy) {
      return $this->proxy->shouldAppearInApplicationTransactions();
    }
    return false;
  }


  /**
   * @task appxaction
   */
  public function getOldValueForApplicationTransactions() {
    if ($this->proxy) {
      return $this->proxy->getOldValueForApplicationTransactions();
    }
    return $this->getValueForStorage();
  }


  /**
   * @task appxaction
   */
  public function getNewValueForApplicationTransactions() {
    if ($this->proxy) {
      return $this->proxy->getNewValueForApplicationTransactions();
    }
    return $this->getValueForStorage();
  }


  /**
   * @task appxaction
   */
  public function setValueFromApplicationTransactions($value) {
    if ($this->proxy) {
      return $this->proxy->setValueFromApplicationTransactions($value);
    }
    return $this->setValueFromStorage($value);
  }


  /**
   * @task appxaction
   */
  public function getNewValueFromApplicationTransactions(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->getNewValueFromApplicationTransactions($xaction);
    }
    return $xaction->getNewValue();
  }


  /**
   * @task appxaction
   */
  public function getApplicationTransactionHasEffect(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionHasEffect($xaction);
    }
    return ($xaction->getOldValue() !== $xaction->getNewValue());
  }


  /**
   * @task appxaction
   */
  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->applyApplicationTransactionInternalEffects($xaction);
    }
    return;
  }


  /**
   * @task appxaction
   */
  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->applyApplicationTransactionExternalEffects($xaction);
    }

    if (!$this->shouldEnableForRole(self::ROLE_STORAGE)) {
      return;
    }

    $this->setValueFromApplicationTransactions($xaction->getNewValue());
    $value = $this->getValueForStorage();

    $table = $this->newStorageObject();
    $conn_w = $table->establishConnection('w');

    if ($value === null) {
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE objectPHID = %s AND fieldIndex = %s',
        $table->getTableName(),
        $this->getObject()->getPHID(),
        $this->getFieldIndex());
    } else {
      queryfx(
        $conn_w,
        'INSERT INTO %T (objectPHID, fieldIndex, fieldValue)
          VALUES (%s, %s, %s)
          ON DUPLICATE KEY UPDATE fieldValue = VALUES(fieldValue)',
        $table->getTableName(),
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
  public function shouldAppearInEditView() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInEditView();
    }
    return false;
  }


  /**
   * @task edit
   */
  public function readValueFromRequest(AphrontRequest $request) {
    if ($this->proxy) {
      return $this->proxy->readValueFromRequest($request);
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * @task edit
   */
  public function renderEditControl() {
    if ($this->proxy) {
      return $this->proxy->renderEditControl();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


/* -(  Property View  )------------------------------------------------------ */


  /**
   * @task view
   */
  public function shouldAppearInPropertyView() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInPropertyView();
    }
    return false;
  }


  /**
   * @task view
   */
  public function renderPropertyViewLabel() {
    if ($this->proxy) {
      return $this->proxy->renderPropertyViewLabel();
    }
    return $this->getFieldName();
  }


  /**
   * @task view
   */
  public function renderPropertyViewValue() {
    if ($this->proxy) {
      return $this->proxy->renderPropertyViewValue();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * @task view
   */
  public function getStyleForPropertyView() {
    if ($this->proxy) {
      return $this->proxy->getStyleForPropertyView();
    }
    return 'property';
  }


/* -(  List View  )---------------------------------------------------------- */


  /**
   * @task list
   */
  public function shouldAppearInListView() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInListView();
    }
    return false;
  }


  /**
   * @task list
   */
  public function renderOnListItem(PHUIObjectItemView $view) {
    if ($this->proxy) {
      return $this->proxy->renderOnListItem($view);
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


}
