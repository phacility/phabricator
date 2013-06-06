<?php

/**
 * @task context    Contextual Data
 * @task storage    Field Storage
 */
abstract class PhabricatorCustomField {

  private $viewer;

  abstract public function getFieldKey();

  public function getFieldIndex() {
    return PhabricatorHash::digestForIndex($this->getFieldKey());
  }

  public function getFieldName() {
    return $this->getFieldKey();
  }

  public function createFields() {
    return array($this);
  }

  public function isFieldEnabled() {
    return true;
  }

  public function canDisableField() {
    return true;
  }

  public static function buildFieldList($base_class, array $spec) {
    $this_class = __CLASS__;
    if (!($base_class instanceof $this_class)) {
      throw new Exception(
        "Base class ('{$base_class}') must extend '{$this_class}'.");
    }

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
      if (!$field->isEnabled()) {
        unset($fields[$key]);
      }
    }

    $fields = array_select_keys($fields, array_keys($spec));

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


/* -(  Contextual Data  )---------------------------------------------------- */


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

}
