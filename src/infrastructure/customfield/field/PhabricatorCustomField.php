<?php

/**
 * @task apps         Building Applications with Custom Fields
 * @task core         Core Properties and Field Identity
 * @task proxy        Field Proxies
 * @task context      Contextual Data
 * @task render       Rendering Utilities
 * @task storage      Field Storage
 * @task edit         Integration with Edit Views
 * @task view         Integration with Property Views
 * @task list         Integration with List views
 * @task appsearch    Integration with ApplicationSearch
 * @task appxaction   Integration with ApplicationTransactions
 * @task xactionmail  Integration with Transaction Mail
 * @task globalsearch Integration with Global Search
 * @task herald       Integration with Herald
 */
abstract class PhabricatorCustomField extends Phobject {

  private $viewer;
  private $object;
  private $proxy;

  const ROLE_APPLICATIONTRANSACTIONS  = 'ApplicationTransactions';
  const ROLE_TRANSACTIONMAIL          = 'ApplicationTransactions.mail';
  const ROLE_APPLICATIONSEARCH        = 'ApplicationSearch';
  const ROLE_STORAGE                  = 'storage';
  const ROLE_DEFAULT                  = 'default';
  const ROLE_EDIT                     = 'edit';
  const ROLE_VIEW                     = 'view';
  const ROLE_LIST                     = 'list';
  const ROLE_GLOBALSEARCH             = 'GlobalSearch';
  const ROLE_CONDUIT                  = 'conduit';
  const ROLE_HERALD                   = 'herald';
  const ROLE_EDITENGINE = 'EditEngine';
  const ROLE_HERALDACTION = 'herald.action';
  const ROLE_EXPORT = 'export';


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
        throw new Exception(
          pht(
            "Expected an array from %s for object of class '%s'.",
            'getCustomFieldSpecificationForRole()',
            get_class($object)));
      }

      $fields = self::buildFieldList(
        $base_class,
        $spec,
        $object);

      $fields = self::adjustCustomFieldsForObjectSubtype(
        $object,
        $role,
        $fields);

      foreach ($fields as $key => $field) {
        // NOTE: We perform this filtering in "buildFieldList()", but may need
        // to filter again after subtype adjustment.
        if (!$field->isFieldEnabled()) {
          unset($fields[$key]);
          continue;
        }

        if (!$field->shouldEnableForRole($role)) {
          unset($fields[$key]);
          continue;
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
  public static function buildFieldList(
    $base_class,
    array $spec,
    $object,
    array $options = array()) {

    $field_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass($base_class)
      ->execute();

    $fields = array();
    foreach ($field_objects as $field_object) {
      $field_object = clone $field_object;
      foreach ($field_object->createFields($object) as $field) {
        $key = $field->getFieldKey();
        if (isset($fields[$key])) {
          throw new Exception(
            pht(
              "Both '%s' and '%s' define a custom field with ".
              "field key '%s'. Field keys must be unique.",
              get_class($fields[$key]),
              get_class($field),
              $key));
        }
        $fields[$key] = $field;
      }
    }

    foreach ($fields as $key => $field) {
      if (!$field->isFieldEnabled()) {
        unset($fields[$key]);
      }
    }

    $fields = array_select_keys($fields, array_keys($spec)) + $fields;

    if (empty($options['withDisabled'])) {
      foreach ($fields as $key => $field) {
        if (isset($spec[$key]['disabled'])) {
          $is_disabled = $spec[$key]['disabled'];
        } else {
          $is_disabled = $field->shouldDisableByDefault();
        }

        if ($is_disabled) {
          if ($field->canDisableField()) {
            unset($fields[$key]);
          }
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

  public function getModernFieldKey() {
    if ($this->proxy) {
      return $this->proxy->getModernFieldKey();
    }
    return $this->getFieldKey();
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
    return $this->getModernFieldKey();
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
   * @param object The object to create fields for.
   * @return list<PhabricatorCustomField> List of fields.
   * @task core
   */
  public function createFields($object) {
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

    // NOTE: All of these calls proxy individually, so we don't need to
    // proxy this call as a whole.

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
      case self::ROLE_GLOBALSEARCH:
        return $this->shouldAppearInGlobalSearch();
      case self::ROLE_CONDUIT:
        return $this->shouldAppearInConduitDictionary();
      case self::ROLE_TRANSACTIONMAIL:
        return $this->shouldAppearInTransactionMail();
      case self::ROLE_HERALD:
        return $this->shouldAppearInHerald();
      case self::ROLE_HERALDACTION:
        return $this->shouldAppearInHeraldActions();
      case self::ROLE_EDITENGINE:
        return $this->shouldAppearInEditView() ||
               $this->shouldAppearInEditEngine();
      case self::ROLE_EXPORT:
        return $this->shouldAppearInDataExport();
      case self::ROLE_DEFAULT:
        return true;
      default:
        throw new Exception(pht("Unknown field role '%s'!", $role));
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

  public function shouldDisableByDefault() {
    return false;
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
   * @return this
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
   * Read object data into local field storage, if applicable.
   *
   * @param PhabricatorCustomFieldInterface The object this field belongs to.
   * @return this
   * @task context
   */
  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    if ($this->proxy) {
      $this->proxy->readValueFromObject($object);
    }
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


/* -(  Rendering Utilities  )------------------------------------------------ */


  /**
   * @task render
   */
  protected function renderHandleList(array $handles) {
    if (!$handles) {
      return null;
    }

    $out = array();
    foreach ($handles as $handle) {
      $out[] = $handle->renderHovercardLink();
    }

    return phutil_implode_html(phutil_tag('br'), $out);
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
    // NOTE: This intentionally isn't proxied, to avoid call cycles.
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

  public function didSetValueFromStorage() {
    if ($this->proxy) {
      return $this->proxy->didSetValueFromStorage();
    }
    return $this;
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
   * Return an index against which this field can be meaningfully ordered
   * against to implement ApplicationSearch.
   *
   * This should be a single index, normally built using
   * @{method:newStringIndex} and @{method:newNumericIndex}.
   *
   * The value of the index is not used.
   *
   * Return null from this method if the field can not be ordered.
   *
   * @return PhabricatorCustomFieldIndexStorage A single index to order by.
   * @task appsearch
   */
  public function buildOrderIndex() {
    if ($this->proxy) {
      return $this->proxy->buildOrderIndex();
    }
    return null;
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
    // NOTE: This intentionally isn't proxied, to avoid call cycles.
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
    // NOTE: This intentionally isn't proxied, to avoid call cycles.
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

    $key = $this->getFieldIndex();
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
    $key = $this->getFieldIndex();
    return $this->newNumericIndexStorage()
      ->setIndexKey($key)
      ->setIndexValue($value);
  }


  /**
   * Read a query value from a request, for storage in a saved query. Normally,
   * this method should, e.g., read a string out of the request.
   *
   * @param PhabricatorApplicationSearchEngine Engine building the query.
   * @param AphrontRequest Request to read from.
   * @return wild
   * @task appsearch
   */
  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {
    if ($this->proxy) {
      return $this->proxy->readApplicationSearchValueFromRequest(
        $engine,
        $request);
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Constrain a query, given a field value. Generally, this method should
   * use `with...()` methods to apply filters or other constraints to the
   * query.
   *
   * @param PhabricatorApplicationSearchEngine Engine executing the query.
   * @param PhabricatorCursorPagedPolicyAwareQuery Query to constrain.
   * @param wild Constraint provided by the user.
   * @return void
   * @task appsearch
   */
  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {
    if ($this->proxy) {
      return $this->proxy->applyApplicationSearchConstraintToQuery(
        $engine,
        $query,
        $value);
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Append search controls to the interface.
   *
   * @param PhabricatorApplicationSearchEngine Engine constructing the form.
   * @param AphrontFormView The form to update.
   * @param wild Value from the saved query.
   * @return void
   * @task appsearch
   */
  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value) {
    if ($this->proxy) {
      return $this->proxy->appendToApplicationSearchForm(
        $engine,
        $form,
        $value);
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
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
  public function getApplicationTransactionType() {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionType();
    }
    return PhabricatorTransactions::TYPE_CUSTOMFIELD;
  }


  /**
   * @task appxaction
   */
  public function getApplicationTransactionMetadata() {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionMetadata();
    }
    return array();
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
  public function getApplicationTransactionRemarkupBlocks(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionRemarkupBlocks($xaction);
    }
    return array();
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


  /**
   * Validate transactions for an object. This allows you to raise an error
   * when a transaction would set a field to an invalid value, or when a field
   * is required but no transactions provide value.
   *
   * @param PhabricatorLiskDAO Editor applying the transactions.
   * @param string Transaction type. This type is always
   *   `PhabricatorTransactions::TYPE_CUSTOMFIELD`, it is provided for
   *   convenience when constructing exceptions.
   * @param list<PhabricatorApplicationTransaction> Transactions being applied,
   *   which may be empty if this field is not being edited.
   * @return list<PhabricatorApplicationTransactionValidationError> Validation
   *   errors.
   *
   * @task appxaction
   */
  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {
    if ($this->proxy) {
      return $this->proxy->validateApplicationTransactions(
        $editor,
        $type,
        $xactions);
    }
    return array();
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionTitle(
        $xaction);
    }

    $author_phid = $xaction->getAuthorPHID();
    return pht(
      '%s updated this object.',
      $xaction->renderHandleLink($author_phid));
  }

  public function getApplicationTransactionTitleForFeed(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionTitleForFeed(
        $xaction);
    }

    $author_phid = $xaction->getAuthorPHID();
    $object_phid = $xaction->getObjectPHID();
    return pht(
      '%s updated %s.',
      $xaction->renderHandleLink($author_phid),
      $xaction->renderHandleLink($object_phid));
  }


  public function getApplicationTransactionHasChangeDetails(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionHasChangeDetails(
        $xaction);
    }
    return false;
  }

  public function getApplicationTransactionChangeDetails(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorUser $viewer) {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionChangeDetails(
        $xaction,
        $viewer);
    }
    return null;
  }

  public function getApplicationTransactionRequiredHandlePHIDs(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->getApplicationTransactionRequiredHandlePHIDs(
        $xaction);
    }
    return array();
  }

  public function shouldHideInApplicationTransactions(
    PhabricatorApplicationTransaction $xaction) {
    if ($this->proxy) {
      return $this->proxy->shouldHideInApplicationTransactions($xaction);
    }
    return false;
  }


/* -(  Transaction Mail  )--------------------------------------------------- */


  /**
   * @task xactionmail
   */
  public function shouldAppearInTransactionMail() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInTransactionMail();
    }
    return false;
  }


  /**
   * @task xactionmail
   */
  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {
    if ($this->proxy) {
      return $this->proxy->updateTransactionMailBody($body, $editor, $xactions);
    }
    return;
  }


/* -(  Edit View  )---------------------------------------------------------- */


  public function getEditEngineFields(PhabricatorEditEngine $engine) {
    $field = $this->newStandardEditField();

    return array(
      $field,
    );
  }

  protected function newEditField() {
    $field = id(new PhabricatorCustomFieldEditField())
      ->setCustomField($this);

    $http_type = $this->getHTTPParameterType();
    if ($http_type) {
      $field->setCustomFieldHTTPParameterType($http_type);
    }

    $conduit_type = $this->getConduitEditParameterType();
    if ($conduit_type) {
      $field->setCustomFieldConduitParameterType($conduit_type);
    }

    $bulk_type = $this->getBulkParameterType();
    if ($bulk_type) {
      $field->setCustomFieldBulkParameterType($bulk_type);
    }

    $comment_action = $this->getCommentAction();
    if ($comment_action) {
      $field
        ->setCustomFieldCommentAction($comment_action)
        ->setCommentActionLabel(
          pht(
            'Change %s',
            $this->getFieldName()));
    }

    return $field;
  }

  protected function newStandardEditField() {
    if ($this->proxy) {
      return $this->proxy->newStandardEditField();
    }

    if ($this->shouldAppearInEditView()) {
      $form_field = true;
    } else {
      $form_field = false;
    }

    $bulk_label = $this->getBulkEditLabel();

    return $this->newEditField()
      ->setKey($this->getFieldKey())
      ->setEditTypeKey($this->getModernFieldKey())
      ->setLabel($this->getFieldName())
      ->setBulkEditLabel($bulk_label)
      ->setDescription($this->getFieldDescription())
      ->setTransactionType($this->getApplicationTransactionType())
      ->setIsFormField($form_field)
      ->setValue($this->getNewValueForApplicationTransactions());
  }

  protected function getBulkEditLabel() {
    if ($this->proxy) {
      return $this->proxy->getBulkEditLabel();
    }

    return pht('Set "%s" to', $this->getFieldName());
  }

  public function getBulkParameterType() {
    return $this->newBulkParameterType();
  }

  protected function newBulkParameterType() {
    if ($this->proxy) {
      return $this->proxy->newBulkParameterType();
    }
    return null;
  }

  protected function getHTTPParameterType() {
    if ($this->proxy) {
      return $this->proxy->getHTTPParameterType();
    }
    return null;
  }

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
  public function shouldAppearInEditEngine() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInEditEngine();
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
  public function getRequiredHandlePHIDsForEdit() {
    if ($this->proxy) {
      return $this->proxy->getRequiredHandlePHIDsForEdit();
    }
    return array();
  }


  /**
   * @task edit
   */
  public function getInstructionsForEdit() {
    if ($this->proxy) {
      return $this->proxy->getInstructionsForEdit();
    }
    return null;
  }


  /**
   * @task edit
   */
  public function renderEditControl(array $handles) {
    if ($this->proxy) {
      return $this->proxy->renderEditControl($handles);
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
  public function renderPropertyViewValue(array $handles) {
    if ($this->proxy) {
      return $this->proxy->renderPropertyViewValue($handles);
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


  /**
   * @task view
   */
  public function getIconForPropertyView() {
    if ($this->proxy) {
      return $this->proxy->getIconForPropertyView();
    }
    return null;
  }


  /**
   * @task view
   */
  public function getRequiredHandlePHIDsForPropertyView() {
    if ($this->proxy) {
      return $this->proxy->getRequiredHandlePHIDsForPropertyView();
    }
    return array();
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


/* -(  Global Search  )------------------------------------------------------ */


  /**
   * @task globalsearch
   */
  public function shouldAppearInGlobalSearch() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInGlobalSearch();
    }
    return false;
  }


  /**
   * @task globalsearch
   */
  public function updateAbstractDocument(
    PhabricatorSearchAbstractDocument $document) {
    if ($this->proxy) {
      return $this->proxy->updateAbstractDocument($document);
    }
    return $document;
  }


/* -(  Data Export  )-------------------------------------------------------- */


  public function shouldAppearInDataExport() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInDataExport();
    }

    try {
      $this->newExportFieldType();
      return true;
    } catch (PhabricatorCustomFieldImplementationIncompleteException $ex) {
      return false;
    }
  }

  public function newExportField() {
    if ($this->proxy) {
      return $this->proxy->newExportField();
    }

    return $this->newExportFieldType()
      ->setLabel($this->getFieldName());
  }

  public function newExportData() {
    if ($this->proxy) {
      return $this->proxy->newExportData();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }

  protected function newExportFieldType() {
    if ($this->proxy) {
      return $this->proxy->newExportFieldType();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


/* -(  Conduit  )------------------------------------------------------------ */


  /**
   * @task conduit
   */
  public function shouldAppearInConduitDictionary() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInConduitDictionary();
    }
    return false;
  }


  /**
   * @task conduit
   */
  public function getConduitDictionaryValue() {
    if ($this->proxy) {
      return $this->proxy->getConduitDictionaryValue();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  public function shouldAppearInConduitTransactions() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInConduitDictionary();
    }
    return false;
  }

  public function getConduitSearchParameterType() {
    return $this->newConduitSearchParameterType();
  }

  protected function newConduitSearchParameterType() {
    if ($this->proxy) {
      return $this->proxy->newConduitSearchParameterType();
    }
    return null;
  }

  public function getConduitEditParameterType() {
    return $this->newConduitEditParameterType();
  }

  protected function newConduitEditParameterType() {
    if ($this->proxy) {
      return $this->proxy->newConduitEditParameterType();
    }
    return null;
  }

  public function getCommentAction() {
    return $this->newCommentAction();
  }

  protected function newCommentAction() {
    if ($this->proxy) {
      return $this->proxy->newCommentAction();
    }
    return null;
  }


/* -(  Herald  )------------------------------------------------------------- */


  /**
   * Return `true` to make this field available in Herald.
   *
   * @return bool True to expose the field in Herald.
   * @task herald
   */
  public function shouldAppearInHerald() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInHerald();
    }
    return false;
  }


  /**
   * Get the name of the field in Herald. By default, this uses the
   * normal field name.
   *
   * @return string Herald field name.
   * @task herald
   */
  public function getHeraldFieldName() {
    if ($this->proxy) {
      return $this->proxy->getHeraldFieldName();
    }
    return $this->getFieldName();
  }


  /**
   * Get the field value for evaluation by Herald.
   *
   * @return wild Field value.
   * @task herald
   */
  public function getHeraldFieldValue() {
    if ($this->proxy) {
      return $this->proxy->getHeraldFieldValue();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Get the available conditions for this field in Herald.
   *
   * @return list<const> List of Herald condition constants.
   * @task herald
   */
  public function getHeraldFieldConditions() {
    if ($this->proxy) {
      return $this->proxy->getHeraldFieldConditions();
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * Get the Herald value type for the given condition.
   *
   * @param   const       Herald condition constant.
   * @return  const|null  Herald value type, or null to use the default.
   * @task herald
   */
  public function getHeraldFieldValueType($condition) {
    if ($this->proxy) {
      return $this->proxy->getHeraldFieldValueType($condition);
    }
    return null;
  }

  public function getHeraldFieldStandardType() {
    if ($this->proxy) {
      return $this->proxy->getHeraldFieldStandardType();
    }
    return null;
  }

  public function getHeraldDatasource() {
    if ($this->proxy) {
      return $this->proxy->getHeraldDatasource();
    }
    return null;
  }


  public function shouldAppearInHeraldActions() {
    if ($this->proxy) {
      return $this->proxy->shouldAppearInHeraldActions();
    }
    return false;
  }


  public function getHeraldActionName() {
    if ($this->proxy) {
      return $this->proxy->getHeraldActionName();
    }

    return null;
  }


  public function getHeraldActionStandardType() {
    if ($this->proxy) {
      return $this->proxy->getHeraldActionStandardType();
    }

    return null;
  }


  public function getHeraldActionDescription($value) {
    if ($this->proxy) {
      return $this->proxy->getHeraldActionDescription($value);
    }

    return null;
  }


  public function getHeraldActionEffectDescription($value) {
    if ($this->proxy) {
      return $this->proxy->getHeraldActionEffectDescription($value);
    }

    return null;
  }


  public function getHeraldActionDatasource() {
    if ($this->proxy) {
      return $this->proxy->getHeraldActionDatasource();
    }

    return null;
  }

  private static function adjustCustomFieldsForObjectSubtype(
    PhabricatorCustomFieldInterface $object,
    $role,
    array $fields) {
    assert_instances_of($fields, __CLASS__);

    // We only apply subtype adjustment for some roles. For example, when
    // writing Herald rules or building a Search interface, we always want to
    // show all the fields in their default state, so we do not apply any
    // adjustments.
    $subtype_roles = array(
      self::ROLE_EDITENGINE,
      self::ROLE_VIEW,
      self::ROLE_EDIT,
    );

    $subtype_roles = array_fuse($subtype_roles);
    if (!isset($subtype_roles[$role])) {
      return $fields;
    }

    // If the object doesn't support subtypes, we can't possibly make
    // any adjustments based on subtype.
    if (!($object instanceof PhabricatorEditEngineSubtypeInterface)) {
      return $fields;
    }

    $subtype_map = $object->newEditEngineSubtypeMap();
    $subtype_key = $object->getEditEngineSubtype();
    $subtype_object = $subtype_map->getSubtype($subtype_key);

    $map = array();
    foreach ($fields as $field) {
      $modern_key = $field->getModernFieldKey();
      if (!strlen($modern_key)) {
        continue;
      }

      $map[$modern_key] = $field;
    }

    foreach ($map as $field_key => $field) {
      // For now, only support overriding standard custom fields. In the
      // future there's no technical or product reason we couldn't let you
      // override (some properites of) other fields like "Title", but they
      // don't usually support appropriate "setX()" methods today.
      if (!($field instanceof PhabricatorStandardCustomField)) {
        // For fields that are proxies on top of StandardCustomField, which
        // is how most application custom fields work today, we can reconfigure
        // the proxied field instead.
        $field = $field->getProxy();
        if (!$field || !($field instanceof PhabricatorStandardCustomField)) {
          continue;
        }
      }

      $subtype_config = $subtype_object->getSubtypeFieldConfiguration(
        $field_key);

      if (!$subtype_config) {
        continue;
      }

      if (isset($subtype_config['disabled'])) {
        $field->setIsEnabled(!$subtype_config['disabled']);
      }

      if (isset($subtype_config['name'])) {
        $field->setFieldName($subtype_config['name']);
      }
    }

    return $fields;
  }

}
