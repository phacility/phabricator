<?php


/**
 * @task fields Managing Fields
 * @task text Display Text
 * @task config Edit Engine Configuration
 * @task uri Managing URIs
 * @task load Creating and Loading Objects
 * @task web Responding to Web Requests
 * @task edit Responding to Edit Requests
 * @task http Responding to HTTP Parameter Requests
 * @task conduit Responding to Conduit Requests
 */
abstract class PhabricatorEditEngine
  extends Phobject
  implements PhabricatorPolicyInterface {

  const EDITENGINECONFIG_DEFAULT = 'default';

  const SUBTYPE_DEFAULT = 'default';

  private $viewer;
  private $controller;
  private $isCreate;
  private $editEngineConfiguration;
  private $contextParameters = array();
  private $targetObject;
  private $page;
  private $pages;
  private $navigation;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    $this->setViewer($controller->getViewer());
    return $this;
  }

  final public function getController() {
    return $this->controller;
  }

  final public function getEngineKey() {
    $key = $this->getPhobjectClassConstant('ENGINECONST', 64);
    if (strpos($key, '/') !== false) {
      throw new Exception(
        pht(
          'EditEngine ("%s") contains an invalid key character "/".',
          get_class($this)));
    }
    return $key;
  }

  final public function getApplication() {
    $app_class = $this->getEngineApplicationClass();
    return PhabricatorApplication::getByClass($app_class);
  }

  final public function addContextParameter($key) {
    $this->contextParameters[] = $key;
    return $this;
  }

  public function isEngineConfigurable() {
    return true;
  }

  public function isEngineExtensible() {
    return true;
  }

  public function isDefaultQuickCreateEngine() {
    return false;
  }

  public function getDefaultQuickCreateFormKeys() {
    $keys = array();

    if ($this->isDefaultQuickCreateEngine()) {
      $keys[] = self::EDITENGINECONFIG_DEFAULT;
    }

    foreach ($keys as $idx => $key) {
      $keys[$idx] = $this->getEngineKey().'/'.$key;
    }

    return $keys;
  }

  public static function splitFullKey($full_key) {
    return explode('/', $full_key, 2);
  }

  public function getQuickCreateOrderVector() {
    return id(new PhutilSortVector())
      ->addString($this->getObjectCreateShortText());
  }

  /**
   * Force the engine to edit a particular object.
   */
  public function setTargetObject($target_object) {
    $this->targetObject = $target_object;
    return $this;
  }

  public function getTargetObject() {
    return $this->targetObject;
  }

  public function setNavigation(AphrontSideNavFilterView $navigation) {
    $this->navigation = $navigation;
    return $this;
  }

  public function getNavigation() {
    return $this->navigation;
  }


/* -(  Managing Fields  )---------------------------------------------------- */


  abstract public function getEngineApplicationClass();
  abstract protected function buildCustomEditFields($object);

  public function getFieldsForConfig(
    PhabricatorEditEngineConfiguration $config) {

    $object = $this->newEditableObject();

    $this->editEngineConfiguration = $config;

    // This is mostly making sure that we fill in default values.
    $this->setIsCreate(true);

    return $this->buildEditFields($object);
  }

  final protected function buildEditFields($object) {
    $viewer = $this->getViewer();

    $fields = $this->buildCustomEditFields($object);

    foreach ($fields as $field) {
      $field
        ->setViewer($viewer)
        ->setObject($object);
    }

    $fields = mpull($fields, null, 'getKey');

    if ($this->isEngineExtensible()) {
      $extensions = PhabricatorEditEngineExtension::getAllEnabledExtensions();
    } else {
      $extensions = array();
    }

    foreach ($extensions as $extension) {
      $extension->setViewer($viewer);

      if (!$extension->supportsObject($this, $object)) {
        continue;
      }

      $extension_fields = $extension->buildCustomEditFields($this, $object);

      // TODO: Validate this in more detail with a more tailored error.
      assert_instances_of($extension_fields, 'PhabricatorEditField');

      foreach ($extension_fields as $field) {
        $field
          ->setViewer($viewer)
          ->setObject($object);
      }

      $extension_fields = mpull($extension_fields, null, 'getKey');

      foreach ($extension_fields as $key => $field) {
        $fields[$key] = $field;
      }
    }

    $config = $this->getEditEngineConfiguration();
    $fields = $this->willConfigureFields($object, $fields);
    $fields = $config->applyConfigurationToFields($this, $object, $fields);

    $fields = $this->applyPageToFields($object, $fields);

    return $fields;
  }

  protected function willConfigureFields($object, array $fields) {
    return $fields;
  }

  final public function supportsSubtypes() {
    try {
      $object = $this->newEditableObject();
    } catch (Exception $ex) {
      return false;
    }

    return ($object instanceof PhabricatorEditEngineSubtypeInterface);
  }

  final public function newSubtypeMap() {
    return $this->newEditableObject()->newEditEngineSubtypeMap();
  }


/* -(  Display Text  )------------------------------------------------------- */


  /**
   * @task text
   */
  abstract public function getEngineName();


  /**
   * @task text
   */
  abstract protected function getObjectCreateTitleText($object);

  /**
   * @task text
   */
  protected function getFormHeaderText($object) {
    $config = $this->getEditEngineConfiguration();
    return $config->getName();
  }

  /**
   * @task text
   */
  abstract protected function getObjectEditTitleText($object);


  /**
   * @task text
   */
  abstract protected function getObjectCreateShortText();


  /**
   * @task text
   */
  abstract protected function getObjectName();


  /**
   * @task text
   */
  abstract protected function getObjectEditShortText($object);


  /**
   * @task text
   */
  protected function getObjectCreateButtonText($object) {
    return $this->getObjectCreateTitleText($object);
  }


  /**
   * @task text
   */
  protected function getObjectEditButtonText($object) {
    return pht('Save Changes');
  }


  /**
   * @task text
   */
  protected function getCommentViewSeriousHeaderText($object) {
    return pht('Take Action');
  }


  /**
   * @task text
   */
  protected function getCommentViewSeriousButtonText($object) {
    return pht('Submit');
  }


  /**
   * @task text
   */
  protected function getCommentViewHeaderText($object) {
    return $this->getCommentViewSeriousHeaderText($object);
  }


  /**
   * @task text
   */
  protected function getCommentViewButtonText($object) {
    return $this->getCommentViewSeriousButtonText($object);
  }


  /**
   * @task text
   */
  protected function getPageHeader($object) {
    return null;
  }



  /**
   * Return a human-readable header describing what this engine is used to do,
   * like "Configure Maniphest Task Forms".
   *
   * @return string Human-readable description of the engine.
   * @task text
   */
  abstract public function getSummaryHeader();


  /**
   * Return a human-readable summary of what this engine is used to do.
   *
   * @return string Human-readable description of the engine.
   * @task text
   */
  abstract public function getSummaryText();




/* -(  Edit Engine Configuration  )------------------------------------------ */


  protected function supportsEditEngineConfiguration() {
    return true;
  }

  final protected function getEditEngineConfiguration() {
    return $this->editEngineConfiguration;
  }

  private function newConfigurationQuery() {
    return id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($this->getViewer())
      ->withEngineKeys(array($this->getEngineKey()));
  }

  private function loadEditEngineConfigurationWithQuery(
    PhabricatorEditEngineConfigurationQuery $query,
    $sort_method) {

    if ($sort_method) {
      $results = $query->execute();
      $results = msort($results, $sort_method);
      $result = head($results);
    } else {
      $result = $query->executeOne();
    }

    if (!$result) {
      return null;
    }

    $this->editEngineConfiguration = $result;
    return $result;
  }

  private function loadEditEngineConfigurationWithIdentifier($identifier) {
    $query = $this->newConfigurationQuery()
      ->withIdentifiers(array($identifier));

    return $this->loadEditEngineConfigurationWithQuery($query, null);
  }

  private function loadDefaultConfiguration() {
    $query = $this->newConfigurationQuery()
      ->withIdentifiers(
        array(
          self::EDITENGINECONFIG_DEFAULT,
        ))
      ->withIgnoreDatabaseConfigurations(true);

    return $this->loadEditEngineConfigurationWithQuery($query, null);
  }

  private function loadDefaultCreateConfiguration() {
    $query = $this->newConfigurationQuery()
      ->withIsDefault(true)
      ->withIsDisabled(false);

    return $this->loadEditEngineConfigurationWithQuery(
      $query,
      'getCreateSortKey');
  }

  public function loadDefaultEditConfiguration($object) {
    $query = $this->newConfigurationQuery()
      ->withIsEdit(true)
      ->withIsDisabled(false);

    // If this object supports subtyping, we edit it with a form of the same
    // subtype: so "bug" tasks get edited with "bug" forms.
    if ($object instanceof PhabricatorEditEngineSubtypeInterface) {
      $query->withSubtypes(
        array(
          $object->getEditEngineSubtype(),
        ));
    }

    return $this->loadEditEngineConfigurationWithQuery(
      $query,
      'getEditSortKey');
  }

  final public function getBuiltinEngineConfigurations() {
    $configurations = $this->newBuiltinEngineConfigurations();

    if (!$configurations) {
      throw new Exception(
        pht(
          'EditEngine ("%s") returned no builtin engine configurations, but '.
          'an edit engine must have at least one configuration.',
          get_class($this)));
    }

    assert_instances_of($configurations, 'PhabricatorEditEngineConfiguration');

    $has_default = false;
    foreach ($configurations as $config) {
      if ($config->getBuiltinKey() == self::EDITENGINECONFIG_DEFAULT) {
        $has_default = true;
      }
    }

    if (!$has_default) {
      $first = head($configurations);
      if (!$first->getBuiltinKey()) {
        $first
          ->setBuiltinKey(self::EDITENGINECONFIG_DEFAULT)
          ->setIsDefault(true)
          ->setIsEdit(true);

        if (!strlen($first->getName())) {
          $first->setName($this->getObjectCreateShortText());
        }
    } else {
        throw new Exception(
          pht(
            'EditEngine ("%s") returned builtin engine configurations, '.
            'but none are marked as default and the first configuration has '.
            'a different builtin key already. Mark a builtin as default or '.
            'omit the key from the first configuration',
            get_class($this)));
      }
    }

    $builtins = array();
    foreach ($configurations as $key => $config) {
      $builtin_key = $config->getBuiltinKey();

      if ($builtin_key === null) {
        throw new Exception(
          pht(
            'EditEngine ("%s") returned builtin engine configurations, '.
            'but one (with key "%s") is missing a builtin key. Provide a '.
            'builtin key for each configuration (you can omit it from the '.
            'first configuration in the list to automatically assign the '.
            'default key).',
            get_class($this),
            $key));
      }

      if (isset($builtins[$builtin_key])) {
        throw new Exception(
          pht(
            'EditEngine ("%s") returned builtin engine configurations, '.
            'but at least two specify the same builtin key ("%s"). Engines '.
            'must have unique builtin keys.',
            get_class($this),
            $builtin_key));
      }

      $builtins[$builtin_key] = $config;
    }


    return $builtins;
  }

  protected function newBuiltinEngineConfigurations() {
    return array(
      $this->newConfiguration(),
    );
  }

  final protected function newConfiguration() {
    return PhabricatorEditEngineConfiguration::initializeNewConfiguration(
      $this->getViewer(),
      $this);
  }


/* -(  Managing URIs  )------------------------------------------------------ */


  /**
   * @task uri
   */
  abstract protected function getObjectViewURI($object);


  /**
   * @task uri
   */
  protected function getObjectCreateCancelURI($object) {
    return $this->getApplication()->getApplicationURI();
  }


  /**
   * @task uri
   */
  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('edit/');
  }


  /**
   * @task uri
   */
  protected function getObjectEditCancelURI($object) {
    return $this->getObjectViewURI($object);
  }


  /**
   * @task uri
   */
  public function getEditURI($object = null, $path = null) {
    $parts = array();

    $parts[] = $this->getEditorURI();

    if ($object && $object->getID()) {
      $parts[] = $object->getID().'/';
    }

    if ($path !== null) {
      $parts[] = $path;
    }

    return implode('', $parts);
  }

  public function getEffectiveObjectViewURI($object) {
    if ($this->getIsCreate()) {
      return $this->getObjectViewURI($object);
    }

    $page = $this->getSelectedPage();
    if ($page) {
      $view_uri = $page->getViewURI();
      if ($view_uri !== null) {
        return $view_uri;
      }
    }

    return $this->getObjectViewURI($object);
  }

  public function getEffectiveObjectEditDoneURI($object) {
    return $this->getEffectiveObjectViewURI($object);
  }

  public function getEffectiveObjectEditCancelURI($object) {
    $page = $this->getSelectedPage();
    if ($page) {
      $view_uri = $page->getViewURI();
      if ($view_uri !== null) {
        return $view_uri;
      }
    }

    return $this->getObjectEditCancelURI($object);
  }


/* -(  Creating and Loading Objects  )--------------------------------------- */


  /**
   * Initialize a new object for creation.
   *
   * @return object Newly initialized object.
   * @task load
   */
  abstract protected function newEditableObject();


  /**
   * Build an empty query for objects.
   *
   * @return PhabricatorPolicyAwareQuery Query.
   * @task load
   */
  abstract protected function newObjectQuery();


  /**
   * Test if this workflow is creating a new object or editing an existing one.
   *
   * @return bool True if a new object is being created.
   * @task load
   */
  final public function getIsCreate() {
    return $this->isCreate;
  }

  /**
   * Initialize a new object for object creation via Conduit.
   *
   * @return object Newly initialized object.
   * @param list<wild> Raw transactions.
   * @task load
   */
  protected function newEditableObjectFromConduit(array $raw_xactions) {
    return $this->newEditableObject();
  }

  /**
   * Initialize a new object for documentation creation.
   *
   * @return object Newly initialized object.
   * @task load
   */
  protected function newEditableObjectForDocumentation() {
    return $this->newEditableObject();
  }

  /**
   * Flag this workflow as a create or edit.
   *
   * @param bool True if this is a create workflow.
   * @return this
   * @task load
   */
  private function setIsCreate($is_create) {
    $this->isCreate = $is_create;
    return $this;
  }


  /**
   * Try to load an object by ID, PHID, or monogram. This is done primarily
   * to make Conduit a little easier to use.
   *
   * @param wild ID, PHID, or monogram.
   * @param list<const> List of required capability constants, or omit for
   *   defaults.
   * @return object Corresponding editable object.
   * @task load
   */
  private function newObjectFromIdentifier(
    $identifier,
    array $capabilities = array()) {
    if (is_int($identifier) || ctype_digit($identifier)) {
      $object = $this->newObjectFromID($identifier, $capabilities);

      if (!$object) {
        throw new Exception(
          pht(
            'No object exists with ID "%s".',
            $identifier));
      }

      return $object;
    }

    $type_unknown = PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN;
    if (phid_get_type($identifier) != $type_unknown) {
      $object = $this->newObjectFromPHID($identifier, $capabilities);

      if (!$object) {
        throw new Exception(
          pht(
            'No object exists with PHID "%s".',
            $identifier));
      }

      return $object;
    }

    $target = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withNames(array($identifier))
      ->executeOne();
    if (!$target) {
      throw new Exception(
        pht(
          'Monogram "%s" does not identify a valid object.',
          $identifier));
    }

    $expect = $this->newEditableObject();
    $expect_class = get_class($expect);
    $target_class = get_class($target);
    if ($expect_class !== $target_class) {
      throw new Exception(
        pht(
          'Monogram "%s" identifies an object of the wrong type. Loaded '.
          'object has class "%s", but this editor operates on objects of '.
          'type "%s".',
          $identifier,
          $target_class,
          $expect_class));
    }

    // Load the object by PHID using this engine's standard query. This makes
    // sure it's really valid, goes through standard policy check logic, and
    // picks up any `need...()` clauses we want it to load with.

    $object = $this->newObjectFromPHID($target->getPHID(), $capabilities);
    if (!$object) {
      throw new Exception(
        pht(
          'Failed to reload object identified by monogram "%s" when '.
          'querying by PHID.',
          $identifier));
    }

    return $object;
  }

  /**
   * Load an object by ID.
   *
   * @param int Object ID.
   * @param list<const> List of required capability constants, or omit for
   *   defaults.
   * @return object|null Object, or null if no such object exists.
   * @task load
   */
  private function newObjectFromID($id, array $capabilities = array()) {
    $query = $this->newObjectQuery()
      ->withIDs(array($id));

    return $this->newObjectFromQuery($query, $capabilities);
  }


  /**
   * Load an object by PHID.
   *
   * @param phid Object PHID.
   * @param list<const> List of required capability constants, or omit for
   *   defaults.
   * @return object|null Object, or null if no such object exists.
   * @task load
   */
  private function newObjectFromPHID($phid, array $capabilities = array()) {
    $query = $this->newObjectQuery()
      ->withPHIDs(array($phid));

    return $this->newObjectFromQuery($query, $capabilities);
  }


  /**
   * Load an object given a configured query.
   *
   * @param PhabricatorPolicyAwareQuery Configured query.
   * @param list<const> List of required capability constants, or omit for
   *  defaults.
   * @return object|null Object, or null if no such object exists.
   * @task load
   */
  private function newObjectFromQuery(
    PhabricatorPolicyAwareQuery $query,
    array $capabilities = array()) {

    $viewer = $this->getViewer();

    if (!$capabilities) {
      $capabilities = array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      );
    }

    $object = $query
      ->setViewer($viewer)
      ->requireCapabilities($capabilities)
      ->executeOne();
    if (!$object) {
      return null;
    }

    return $object;
  }


  /**
   * Verify that an object is appropriate for editing.
   *
   * @param wild Loaded value.
   * @return void
   * @task load
   */
  private function validateObject($object) {
    if (!$object || !is_object($object)) {
      throw new Exception(
        pht(
          'EditEngine "%s" created or loaded an invalid object: object must '.
          'actually be an object, but is of some other type ("%s").',
          get_class($this),
          gettype($object)));
    }

    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      throw new Exception(
        pht(
          'EditEngine "%s" created or loaded an invalid object: object (of '.
          'class "%s") must implement "%s", but does not.',
          get_class($this),
          get_class($object),
          'PhabricatorApplicationTransactionInterface'));
    }
  }


/* -(  Responding to Web Requests  )----------------------------------------- */


  final public function buildResponse() {
    $viewer = $this->getViewer();
    $controller = $this->getController();
    $request = $controller->getRequest();

    $action = $this->getEditAction();

    $capabilities = array();
    $use_default = false;
    $require_create = true;
    switch ($action) {
      case 'comment':
        $capabilities = array(
          PhabricatorPolicyCapability::CAN_VIEW,
        );
        $use_default = true;
        break;
      case 'parameters':
        $use_default = true;
        break;
      case 'nodefault':
      case 'nocreate':
      case 'nomanage':
        $require_create = false;
        break;
      default:
        break;
    }

    $object = $this->getTargetObject();
    if (!$object) {
      $id = $request->getURIData('id');

      if ($id) {
        $this->setIsCreate(false);
        $object = $this->newObjectFromID($id, $capabilities);
        if (!$object) {
          return new Aphront404Response();
        }
      } else {
        // Make sure the viewer has permission to create new objects of
        // this type if we're going to create a new object.
        if ($require_create) {
          $this->requireCreateCapability();
        }

        $this->setIsCreate(true);
        $object = $this->newEditableObject();
      }
    } else {
      $id = $object->getID();
    }

    $this->validateObject($object);

    if ($use_default) {
      $config = $this->loadDefaultConfiguration();
      if (!$config) {
        return new Aphront404Response();
      }
    } else {
      $form_key = $request->getURIData('formKey');
      if (strlen($form_key)) {
        $config = $this->loadEditEngineConfigurationWithIdentifier($form_key);

        if (!$config) {
          return new Aphront404Response();
        }

        if ($id && !$config->getIsEdit()) {
          return $this->buildNotEditFormRespose($object, $config);
        }
      } else {
        if ($id) {
          $config = $this->loadDefaultEditConfiguration($object);
          if (!$config) {
            return $this->buildNoEditResponse($object);
          }
        } else {
          $config = $this->loadDefaultCreateConfiguration();
          if (!$config) {
            return $this->buildNoCreateResponse($object);
          }
        }
      }
    }

    if ($config->getIsDisabled()) {
      return $this->buildDisabledFormResponse($object, $config);
    }

    $page_key = $request->getURIData('pageKey');
    if (!strlen($page_key)) {
      $pages = $this->getPages($object);
      if ($pages) {
        $page_key = head_key($pages);
      }
    }

    if (strlen($page_key)) {
      $page = $this->selectPage($object, $page_key);
      if (!$page) {
        return new Aphront404Response();
      }
    }

    switch ($action) {
      case 'parameters':
        return $this->buildParametersResponse($object);
      case 'nodefault':
        return $this->buildNoDefaultResponse($object);
      case 'nocreate':
        return $this->buildNoCreateResponse($object);
      case 'nomanage':
        return $this->buildNoManageResponse($object);
      case 'comment':
        return $this->buildCommentResponse($object);
      default:
        return $this->buildEditResponse($object);
    }
  }

  private function buildCrumbs($object, $final = false) {
    $controller = $this->getController();

    $crumbs = $controller->buildApplicationCrumbsForEditEngine();
    if ($this->getIsCreate()) {
      $create_text = $this->getObjectCreateShortText();
      if ($final) {
        $crumbs->addTextCrumb($create_text);
      } else {
        $edit_uri = $this->getEditURI($object);
        $crumbs->addTextCrumb($create_text, $edit_uri);
      }
    } else {
      $crumbs->addTextCrumb(
        $this->getObjectEditShortText($object),
        $this->getEffectiveObjectViewURI($object));

      $edit_text = pht('Edit');
      if ($final) {
        $crumbs->addTextCrumb($edit_text);
      } else {
        $edit_uri = $this->getEditURI($object);
        $crumbs->addTextCrumb($edit_text, $edit_uri);
      }
    }

    return $crumbs;
  }

  private function buildEditResponse($object) {
    $viewer = $this->getViewer();
    $controller = $this->getController();
    $request = $controller->getRequest();

    $fields = $this->buildEditFields($object);
    $template = $object->getApplicationTransactionTemplate();

    if ($this->getIsCreate()) {
      $cancel_uri = $this->getObjectCreateCancelURI($object);
      $submit_button = $this->getObjectCreateButtonText($object);
    } else {
      $cancel_uri = $this->getEffectiveObjectEditCancelURI($object);
      $submit_button = $this->getObjectEditButtonText($object);
    }

    $config = $this->getEditEngineConfiguration()
      ->attachEngine($this);

    // NOTE: Don't prompt users to override locks when creating objects,
    // even if the default settings would create a locked object.

    $can_interact = PhabricatorPolicyFilter::canInteract($viewer, $object);
    if (!$can_interact &&
        !$this->getIsCreate() &&
        !$request->getBool('editEngine') &&
        !$request->getBool('overrideLock')) {

      $lock = PhabricatorEditEngineLock::newForObject($viewer, $object);

      $dialog = $this->getController()
        ->newDialog()
        ->addHiddenInput('overrideLock', true)
        ->setDisableWorkflowOnSubmit(true)
        ->addCancelButton($cancel_uri);

      return $lock->willPromptUserForLockOverrideWithDialog($dialog);
    }

    $validation_exception = null;
    if ($request->isFormPost() && $request->getBool('editEngine')) {
      $submit_fields = $fields;

      foreach ($submit_fields as $key => $field) {
        if (!$field->shouldGenerateTransactionsFromSubmit()) {
          unset($submit_fields[$key]);
          continue;
        }
      }

      // Before we read the submitted values, store a copy of what we would
      // use if the form was empty so we can figure out which transactions are
      // just setting things to their default values for the current form.
      $defaults = array();
      foreach ($submit_fields as $key => $field) {
        $defaults[$key] = $field->getValueForTransaction();
      }

      foreach ($submit_fields as $key => $field) {
        $field->setIsSubmittedForm(true);

        if (!$field->shouldReadValueFromSubmit()) {
          continue;
        }

        $field->readValueFromSubmit($request);
      }

      $xactions = array();

      if ($this->getIsCreate()) {
        $xactions[] = id(clone $template)
          ->setTransactionType(PhabricatorTransactions::TYPE_CREATE);

        if ($this->supportsSubtypes()) {
          $xactions[] = id(clone $template)
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBTYPE)
            ->setNewValue($config->getSubtype());
        }
      }

      foreach ($submit_fields as $key => $field) {
        $field_value = $field->getValueForTransaction();

        $type_xactions = $field->generateTransactions(
          clone $template,
          array(
            'value' => $field_value,
          ));

        foreach ($type_xactions as $type_xaction) {
          $default = $defaults[$key];

          if ($default === $field->getValueForTransaction()) {
            $type_xaction->setIsDefaultTransaction(true);
          }

          $xactions[] = $type_xaction;
        }
      }

      $editor = $object->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $xactions = $this->willApplyTransactions($object, $xactions);

        $editor->applyTransactions($object, $xactions);

        $this->didApplyTransactions($object, $xactions);

        return $this->newEditResponse($request, $object, $xactions);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        foreach ($fields as $field) {
          $message = $this->getValidationExceptionShortMessage($ex, $field);
          if ($message === null) {
            continue;
          }

          $field->setControlError($message);
        }
      }
    } else {
      if ($this->getIsCreate()) {
        $template = $request->getStr('template');

        if (strlen($template)) {
          $template_object = $this->newObjectFromIdentifier(
            $template,
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
            ));
          if (!$template_object) {
            return new Aphront404Response();
          }
        } else {
          $template_object = null;
        }

        if ($template_object) {
          $copy_fields = $this->buildEditFields($template_object);
          $copy_fields = mpull($copy_fields, null, 'getKey');
          foreach ($copy_fields as $copy_key => $copy_field) {
            if (!$copy_field->getIsCopyable()) {
              unset($copy_fields[$copy_key]);
            }
          }
        } else {
          $copy_fields = array();
        }

        foreach ($fields as $field) {
          if (!$field->shouldReadValueFromRequest()) {
            continue;
          }

          $field_key = $field->getKey();
          if (isset($copy_fields[$field_key])) {
            $field->readValueFromField($copy_fields[$field_key]);
          }

          $field->readValueFromRequest($request);
        }
      }
    }

    $action_button = $this->buildEditFormActionButton($object);

    if ($this->getIsCreate()) {
      $header_text = $this->getFormHeaderText($object);
    } else {
      $header_text = $this->getObjectEditTitleText($object);
    }

    $show_preview = !$request->isAjax();

    if ($show_preview) {
      $previews = array();
      foreach ($fields as $field) {
        $preview = $field->getPreviewPanel();
        if (!$preview) {
          continue;
        }

        $control_id = $field->getControlID();

        $preview
          ->setControlID($control_id)
          ->setPreviewURI('/transactions/remarkuppreview/');

        $previews[] = $preview;
      }
    } else {
      $previews = array();
    }

    $form = $this->buildEditForm($object, $fields);

    $crumbs = $this->buildCrumbs($object, $final = true);
    $crumbs->setBorder(true);

    if ($request->isAjax()) {
      return $this->getController()
        ->newDialog()
        ->setWidth(AphrontDialogView::WIDTH_FULL)
        ->setTitle($header_text)
        ->setValidationException($validation_exception)
        ->appendForm($form)
        ->addCancelButton($cancel_uri)
        ->addSubmitButton($submit_button);
    }

    $box_header = id(new PHUIHeaderView())
      ->setHeader($header_text);

    if ($action_button) {
      $box_header->addActionLink($action_button);
    }

    $box = id(new PHUIObjectBoxView())
      ->setUser($viewer)
      ->setHeader($box_header)
      ->setValidationException($validation_exception)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->appendChild($form);

    // This is fairly questionable, but in use by Settings.
    if ($request->getURIData('formSaved')) {
      $box->setFormSaved(true);
    }

    $content = array(
      $box,
      $previews,
    );

    $view = new PHUITwoColumnView();

    $page_header = $this->getPageHeader($object);
    if ($page_header) {
      $view->setHeader($page_header);
    }

    $page = $controller->newPage()
      ->setTitle($header_text)
      ->setCrumbs($crumbs)
      ->appendChild($view);

    $navigation = $this->getNavigation();
    if ($navigation) {
      $view->setFixed(true);
      $view->setNavigation($navigation);
      $view->setMainColumn($content);
    } else {
      $view->setFooter($content);
    }

    return $page;
  }

  protected function newEditResponse(
    AphrontRequest $request,
    $object,
    array $xactions) {
    return id(new AphrontRedirectResponse())
      ->setURI($this->getEffectiveObjectEditDoneURI($object));
  }

  private function buildEditForm($object, array $fields) {
    $viewer = $this->getViewer();
    $controller = $this->getController();
    $request = $controller->getRequest();

    $fields = $this->willBuildEditForm($object, $fields);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('editEngine', 'true');

    foreach ($this->contextParameters as $param) {
      $form->addHiddenInput($param, $request->getStr($param));
    }

    foreach ($fields as $field) {
      $field->appendToForm($form);
    }

    if ($this->getIsCreate()) {
      $cancel_uri = $this->getObjectCreateCancelURI($object);
      $submit_button = $this->getObjectCreateButtonText($object);
    } else {
      $cancel_uri = $this->getEffectiveObjectEditCancelURI($object);
      $submit_button = $this->getObjectEditButtonText($object);
    }

    if (!$request->isAjax()) {
      $buttons = id(new AphrontFormSubmitControl())
        ->setValue($submit_button);

      if ($cancel_uri) {
        $buttons->addCancelButton($cancel_uri);
      }

      $form->appendControl($buttons);
    }

    return $form;
  }

  protected function willBuildEditForm($object, array $fields) {
    return $fields;
  }

  private function buildEditFormActionButton($object) {
    if (!$this->isEngineConfigurable()) {
      return null;
    }

    $viewer = $this->getViewer();

    $action_view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    foreach ($this->buildEditFormActions($object) as $action) {
      $action_view->addAction($action);
    }

    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Configure Form'))
      ->setHref('#')
      ->setIcon('fa-gear')
      ->setDropdownMenu($action_view);

    return $action_button;
  }

  private function buildEditFormActions($object) {
    $actions = array();

    if ($this->supportsEditEngineConfiguration()) {
      $engine_key = $this->getEngineKey();
      $config = $this->getEditEngineConfiguration();

      $can_manage = PhabricatorPolicyFilter::hasCapability(
        $this->getViewer(),
        $config,
        PhabricatorPolicyCapability::CAN_EDIT);

      if ($can_manage) {
        $manage_uri = $config->getURI();
      } else {
        $manage_uri = $this->getEditURI(null, 'nomanage/');
      }

      $view_uri = "/transactions/editengine/{$engine_key}/";

      $actions[] = id(new PhabricatorActionView())
        ->setLabel(true)
        ->setName(pht('Configuration'));

      $actions[] = id(new PhabricatorActionView())
        ->setName(pht('View Form Configurations'))
        ->setIcon('fa-list-ul')
        ->setHref($view_uri);

      $actions[] = id(new PhabricatorActionView())
        ->setName(pht('Edit Form Configuration'))
        ->setIcon('fa-pencil')
        ->setHref($manage_uri)
        ->setDisabled(!$can_manage)
        ->setWorkflow(!$can_manage);
    }

    $actions[] = id(new PhabricatorActionView())
      ->setLabel(true)
      ->setName(pht('Documentation'));

    $actions[] = id(new PhabricatorActionView())
      ->setName(pht('Using HTTP Parameters'))
      ->setIcon('fa-book')
      ->setHref($this->getEditURI($object, 'parameters/'));

    $doc_href = PhabricatorEnv::getDoclink('User Guide: Customizing Forms');
    $actions[] = id(new PhabricatorActionView())
      ->setName(pht('User Guide: Customizing Forms'))
      ->setIcon('fa-book')
      ->setHref($doc_href);

    return $actions;
  }


  /**
   * Test if the viewer could apply a certain type of change by using the
   * normal "Edit" form.
   *
   * This method returns `true` if the user has access to an edit form and
   * that edit form has a field which applied the specified transaction type,
   * and that field is visible and editable for the user.
   *
   * For example, you can use it to test if a user is able to reassign tasks
   * or not, prior to rendering dedicated UI for task reassignment.
   *
   * Note that this method does NOT test if the user can actually edit the
   * current object, just if they have access to the related field.
   *
   * @param const Transaction type to test for.
   * @return bool True if the user could "Edit" to apply the transaction type.
   */
  final public function hasEditAccessToTransaction($xaction_type) {
    $viewer = $this->getViewer();

    $object = $this->getTargetObject();
    if (!$object) {
      $object = $this->newEditableObject();
    }

    $config = $this->loadDefaultEditConfiguration($object);
    if (!$config) {
      return false;
    }

    $fields = $this->buildEditFields($object);

    $field = null;
    foreach ($fields as $form_field) {
      $field_xaction_type = $form_field->getTransactionType();
      if ($field_xaction_type === $xaction_type) {
        $field = $form_field;
        break;
      }
    }

    if (!$field) {
      return false;
    }

    if (!$field->shouldReadValueFromSubmit()) {
      return false;
    }

    return true;
  }


  public function newNUXButton($text) {
    $specs = $this->newCreateActionSpecifications(array());
    $head = head($specs);

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setText($text)
      ->setHref($head['uri'])
      ->setDisabled($head['disabled'])
      ->setWorkflow($head['workflow'])
      ->setColor(PHUIButtonView::GREEN);
  }


  final public function addActionToCrumbs(
    PHUICrumbsView $crumbs,
    array $parameters = array()) {
    $viewer = $this->getViewer();

    $specs = $this->newCreateActionSpecifications($parameters);

    $head = head($specs);
    $menu_uri = $head['uri'];

    $dropdown = null;
    if (count($specs) > 1) {
      $menu_icon = 'fa-caret-square-o-down';
      $menu_name = $this->getObjectCreateShortText();
      $workflow = false;
      $disabled = false;

      $dropdown = id(new PhabricatorActionListView())
        ->setUser($viewer);

      foreach ($specs as $spec) {
        $dropdown->addAction(
          id(new PhabricatorActionView())
            ->setName($spec['name'])
            ->setIcon($spec['icon'])
            ->setHref($spec['uri'])
            ->setDisabled($head['disabled'])
            ->setWorkflow($head['workflow']));
      }

    } else {
      $menu_icon = $head['icon'];
      $menu_name = $head['name'];

      $workflow = $head['workflow'];
      $disabled = $head['disabled'];
    }

    $action = id(new PHUIListItemView())
      ->setName($menu_name)
      ->setHref($menu_uri)
      ->setIcon($menu_icon)
      ->setWorkflow($workflow)
      ->setDisabled($disabled);

    if ($dropdown) {
      $action->setDropdownMenu($dropdown);
    }

    $crumbs->addAction($action);
  }


  /**
   * Build a raw description of available "Create New Object" UI options so
   * other methods can build menus or buttons.
   */
  public function newCreateActionSpecifications(array $parameters) {
    $viewer = $this->getViewer();

    $can_create = $this->hasCreateCapability();
    if ($can_create) {
      $configs = $this->loadUsableConfigurationsForCreate();
    } else {
      $configs = array();
    }

    $disabled = false;
    $workflow = false;

    $menu_icon = 'fa-plus-square';
    $specs = array();
    if (!$configs) {
      if ($viewer->isLoggedIn()) {
        $disabled = true;
      } else {
        // If the viewer isn't logged in, assume they'll get hit with a login
        // dialog and are likely able to create objects after they log in.
        $disabled = false;
      }
      $workflow = true;

      if ($can_create) {
        $create_uri = $this->getEditURI(null, 'nodefault/');
      } else {
        $create_uri = $this->getEditURI(null, 'nocreate/');
      }

      $specs[] = array(
        'name' => $this->getObjectCreateShortText(),
        'uri' => $create_uri,
        'icon' => $menu_icon,
        'disabled' => $disabled,
        'workflow' => $workflow,
      );
    } else {
      foreach ($configs as $config) {
        $config_uri = $config->getCreateURI();

        if ($parameters) {
          $config_uri = (string)id(new PhutilURI($config_uri))
            ->setQueryParams($parameters);
        }

        $specs[] = array(
          'name' => $config->getDisplayName(),
          'uri' => $config_uri,
          'icon' => 'fa-plus',
          'disabled' => false,
          'workflow' => false,
        );
      }
    }

    return $specs;
  }

  final public function buildEditEngineCommentView($object) {
    $config = $this->loadDefaultEditConfiguration($object);

    if (!$config) {
      // TODO: This just nukes the entire comment form if you don't have access
      // to any edit forms. We might want to tailor this UX a bit.
      return id(new PhabricatorApplicationTransactionCommentView())
        ->setNoPermission(true);
    }

    $viewer = $this->getViewer();

    $can_interact = PhabricatorPolicyFilter::canInteract($viewer, $object);
    if (!$can_interact) {
      $lock = PhabricatorEditEngineLock::newForObject($viewer, $object);

      return id(new PhabricatorApplicationTransactionCommentView())
        ->setEditEngineLock($lock);
    }

    $object_phid = $object->getPHID();
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    if ($is_serious) {
      $header_text = $this->getCommentViewSeriousHeaderText($object);
      $button_text = $this->getCommentViewSeriousButtonText($object);
    } else {
      $header_text = $this->getCommentViewHeaderText($object);
      $button_text = $this->getCommentViewButtonText($object);
    }

    $comment_uri = $this->getEditURI($object, 'comment/');

    $view = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($object_phid)
      ->setHeaderText($header_text)
      ->setAction($comment_uri)
      ->setSubmitButtonName($button_text);

    $draft = PhabricatorVersionedDraft::loadDraft(
      $object_phid,
      $viewer->getPHID());
    if ($draft) {
      $view->setVersionedDraft($draft);
    }

    $view->setCurrentVersion($this->loadDraftVersion($object));

    $fields = $this->buildEditFields($object);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    $comment_actions = array();
    foreach ($fields as $field) {
      if (!$field->shouldGenerateTransactionsFromComment()) {
        continue;
      }

      if (!$can_edit) {
        if (!$field->getCanApplyWithoutEditCapability()) {
          continue;
        }
      }

      $comment_action = $field->getCommentAction();
      if (!$comment_action) {
        continue;
      }

      $key = $comment_action->getKey();

      // TODO: Validate these better.

      $comment_actions[$key] = $comment_action;
    }

    $comment_actions = msortv($comment_actions, 'getSortVector');

    $view->setCommentActions($comment_actions);

    $comment_groups = $this->newCommentActionGroups();
    $view->setCommentActionGroups($comment_groups);

    return $view;
  }

  protected function loadDraftVersion($object) {
    $viewer = $this->getViewer();

    if (!$viewer->isLoggedIn()) {
      return null;
    }

    $template = $object->getApplicationTransactionTemplate();
    $conn_r = $template->establishConnection('r');

    // Find the most recent transaction the user has written. We'll use this
    // as a version number to make sure that out-of-date drafts get discarded.
    $result = queryfx_one(
      $conn_r,
      'SELECT id AS version FROM %T
        WHERE objectPHID = %s AND authorPHID = %s
        ORDER BY id DESC LIMIT 1',
      $template->getTableName(),
      $object->getPHID(),
      $viewer->getPHID());

    if ($result) {
      return (int)$result['version'];
    } else {
      return null;
    }
  }


/* -(  Responding to HTTP Parameter Requests  )------------------------------ */


  /**
   * Respond to a request for documentation on HTTP parameters.
   *
   * @param object Editable object.
   * @return AphrontResponse Response object.
   * @task http
   */
  private function buildParametersResponse($object) {
    $controller = $this->getController();
    $viewer = $this->getViewer();
    $request = $controller->getRequest();
    $fields = $this->buildEditFields($object);

    $crumbs = $this->buildCrumbs($object);
    $crumbs->addTextCrumb(pht('HTTP Parameters'));
    $crumbs->setBorder(true);

    $header_text = pht(
      'HTTP Parameters: %s',
      $this->getObjectCreateShortText());

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text);

    $help_view = id(new PhabricatorApplicationEditHTTPParameterHelpView())
      ->setUser($viewer)
      ->setFields($fields);

    $document = id(new PHUIDocumentViewPro())
      ->setUser($viewer)
      ->setHeader($header)
      ->appendChild($help_view);

    return $controller->newPage()
      ->setTitle(pht('HTTP Parameters'))
      ->setCrumbs($crumbs)
      ->appendChild($document);
  }


  private function buildError($object, $title, $body) {
    $cancel_uri = $this->getObjectCreateCancelURI($object);

    $dialog = $this->getController()
      ->newDialog()
      ->addCancelButton($cancel_uri);

    if ($title !== null) {
      $dialog->setTitle($title);
    }

    if ($body !== null) {
      $dialog->appendParagraph($body);
    }

    return $dialog;
  }


  private function buildNoDefaultResponse($object) {
    return $this->buildError(
      $object,
      pht('No Default Create Forms'),
      pht(
        'This application is not configured with any forms for creating '.
        'objects that are visible to you and enabled.'));
  }

  private function buildNoCreateResponse($object) {
    return $this->buildError(
      $object,
      pht('No Create Permission'),
      pht('You do not have permission to create these objects.'));
  }

  private function buildNoManageResponse($object) {
    return $this->buildError(
      $object,
      pht('No Manage Permission'),
      pht(
        'You do not have permission to configure forms for this '.
        'application.'));
  }

  private function buildNoEditResponse($object) {
    return $this->buildError(
      $object,
      pht('No Edit Forms'),
      pht(
        'You do not have access to any forms which are enabled and marked '.
        'as edit forms.'));
  }

  private function buildNotEditFormRespose($object, $config) {
    return $this->buildError(
      $object,
      pht('Not an Edit Form'),
      pht(
        'This form ("%s") is not marked as an edit form, so '.
        'it can not be used to edit objects.',
        $config->getName()));
  }

  private function buildDisabledFormResponse($object, $config) {
    return $this->buildError(
      $object,
      pht('Form Disabled'),
      pht(
        'This form ("%s") has been disabled, so it can not be used.',
        $config->getName()));
  }

  private function buildLockedObjectResponse($object) {
    $dialog = $this->buildError($object, null, null);
    $viewer = $this->getViewer();

    $lock = PhabricatorEditEngineLock::newForObject($viewer, $object);
    return $lock->willBlockUserInteractionWithDialog($dialog);
  }

  private function buildCommentResponse($object) {
    $viewer = $this->getViewer();

    if ($this->getIsCreate()) {
      return new Aphront404Response();
    }

    $controller = $this->getController();
    $request = $controller->getRequest();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $can_interact = PhabricatorPolicyFilter::canInteract($viewer, $object);
    if (!$can_interact) {
      return $this->buildLockedObjectResponse($object);
    }

    $config = $this->loadDefaultEditConfiguration($object);
    if (!$config) {
      return new Aphront404Response();
    }

    $fields = $this->buildEditFields($object);

    $is_preview = $request->isPreviewRequest();
    $view_uri = $this->getEffectiveObjectViewURI($object);

    $template = $object->getApplicationTransactionTemplate();
    $comment_template = $template->getApplicationTransactionCommentObject();

    $comment_text = $request->getStr('comment');

    $actions = $request->getStr('editengine.actions');
    if ($actions) {
      $actions = phutil_json_decode($actions);
    }

    if ($is_preview) {
      $version_key = PhabricatorVersionedDraft::KEY_VERSION;
      $request_version = $request->getInt($version_key);
      $current_version = $this->loadDraftVersion($object);
      if ($request_version >= $current_version) {
        $draft = PhabricatorVersionedDraft::loadOrCreateDraft(
          $object->getPHID(),
          $viewer->getPHID(),
          $current_version);

        $is_empty = (!strlen($comment_text) && !$actions);

        $draft
          ->setProperty('comment', $comment_text)
          ->setProperty('actions', $actions)
          ->save();

        $draft_engine = $this->newDraftEngine($object);
        if ($draft_engine) {
          $draft_engine
            ->setVersionedDraft($draft)
            ->synchronize();
        }
      }
    }

    $xactions = array();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($actions) {
      $action_map = array();
      foreach ($actions as $action) {
        $type = idx($action, 'type');
        if (!$type) {
          continue;
        }

        if (empty($fields[$type])) {
          continue;
        }

        $action_map[$type] = $action;
      }

      foreach ($action_map as $type => $action) {
        $field = $fields[$type];

        if (!$field->shouldGenerateTransactionsFromComment()) {
          continue;
        }

        // If you don't have edit permission on the object, you're limited in
        // which actions you can take via the comment form. Most actions
        // need edit permission, but some actions (like "Accept Revision")
        // can be applied by anyone with view permission.
        if (!$can_edit) {
          if (!$field->getCanApplyWithoutEditCapability()) {
            // We know the user doesn't have the capability, so this will
            // raise a policy exception.
            PhabricatorPolicyFilter::requireCapability(
              $viewer,
              $object,
              PhabricatorPolicyCapability::CAN_EDIT);
          }
        }

        if (array_key_exists('initialValue', $action)) {
          $field->setInitialValue($action['initialValue']);
        }

        $field->readValueFromComment(idx($action, 'value'));

        $type_xactions = $field->generateTransactions(
          clone $template,
          array(
            'value' => $field->getValueForTransaction(),
          ));
        foreach ($type_xactions as $type_xaction) {
          $xactions[] = $type_xaction;
        }
      }
    }

    $auto_xactions = $this->newAutomaticCommentTransactions($object);
    foreach ($auto_xactions as $xaction) {
      $xactions[] = $xaction;
    }

    if (strlen($comment_text) || !$xactions) {
      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(clone $comment_template)
            ->setContent($comment_text));
    }

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContinueOnMissingFields(true)
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($object, $xactions);
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      return id(new PhabricatorApplicationTransactionValidationResponse())
        ->setCancelURI($view_uri)
        ->setException($ex);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($view_uri)
        ->setException($ex);
    }

    if (!$is_preview) {
      PhabricatorVersionedDraft::purgeDrafts(
        $object->getPHID(),
        $viewer->getPHID(),
        $this->loadDraftVersion($object));

      $draft_engine = $this->newDraftEngine($object);
      if ($draft_engine) {
        $draft_engine
          ->setVersionedDraft(null)
          ->synchronize();
      }
    }

    if ($request->isAjax() && $is_preview) {
      $preview_content = $this->newCommentPreviewContent($object, $xactions);

      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($viewer)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview)
        ->setPreviewContent($preview_content);
    } else {
      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }
  }

  protected function newDraftEngine($object) {
    $viewer = $this->getViewer();

    if ($object instanceof PhabricatorDraftInterface) {
      $engine = $object->newDraftEngine();
    } else {
      $engine = new PhabricatorBuiltinDraftEngine();
    }

    return $engine
      ->setObject($object)
      ->setViewer($viewer);
  }


/* -(  Conduit  )------------------------------------------------------------ */


  /**
   * Respond to a Conduit edit request.
   *
   * This method accepts a list of transactions to apply to an object, and
   * either edits an existing object or creates a new one.
   *
   * @task conduit
   */
  final public function buildConduitResponse(ConduitAPIRequest $request) {
    $viewer = $this->getViewer();

    $config = $this->loadDefaultConfiguration();
    if (!$config) {
      throw new Exception(
        pht(
          'Unable to load configuration for this EditEngine ("%s").',
          get_class($this)));
    }

    $raw_xactions = $this->getRawConduitTransactions($request);

    $identifier = $request->getValue('objectIdentifier');
    if ($identifier) {
      $this->setIsCreate(false);
      $object = $this->newObjectFromIdentifier($identifier);
    } else {
      $this->requireCreateCapability();

      $this->setIsCreate(true);
      $object = $this->newEditableObjectFromConduit($raw_xactions);
    }

    $this->validateObject($object);

    $fields = $this->buildEditFields($object);

    $types = $this->getConduitEditTypesFromFields($fields);
    $template = $object->getApplicationTransactionTemplate();

    $xactions = $this->getConduitTransactions(
      $request,
      $raw_xactions,
      $types,
      $template);

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setContentSource($request->newContentSource())
      ->setContinueOnNoEffect(true);

    if (!$this->getIsCreate()) {
      $editor->setContinueOnMissingFields(true);
    }

    $xactions = $editor->applyTransactions($object, $xactions);

    $xactions_struct = array();
    foreach ($xactions as $xaction) {
      $xactions_struct[] = array(
        'phid' => $xaction->getPHID(),
      );
    }

    return array(
      'object' => array(
        'id' => $object->getID(),
        'phid' => $object->getPHID(),
      ),
      'transactions' => $xactions_struct,
    );
  }

  private function getRawConduitTransactions(ConduitAPIRequest $request) {
    $transactions_key = 'transactions';

    $xactions = $request->getValue($transactions_key);
    if (!is_array($xactions)) {
      throw new Exception(
        pht(
          'Parameter "%s" is not a list of transactions.',
          $transactions_key));
    }

    foreach ($xactions as $key => $xaction) {
      if (!is_array($xaction)) {
        throw new Exception(
          pht(
            'Parameter "%s" must contain a list of transaction descriptions, '.
            'but item with key "%s" is not a dictionary.',
            $transactions_key,
            $key));
      }

      if (!array_key_exists('type', $xaction)) {
        throw new Exception(
          pht(
            'Parameter "%s" must contain a list of transaction descriptions, '.
            'but item with key "%s" is missing a "type" field. Each '.
            'transaction must have a type field.',
            $transactions_key,
            $key));
      }
    }

    return $xactions;
  }


  /**
   * Generate transactions which can be applied from edit actions in a Conduit
   * request.
   *
   * @param ConduitAPIRequest The request.
   * @param list<wild> Raw conduit transactions.
   * @param list<PhabricatorEditType> Supported edit types.
   * @param PhabricatorApplicationTransaction Template transaction.
   * @return list<PhabricatorApplicationTransaction> Generated transactions.
   * @task conduit
   */
  private function getConduitTransactions(
    ConduitAPIRequest $request,
    array $xactions,
    array $types,
    PhabricatorApplicationTransaction $template) {

    $viewer = $request->getUser();
    $results = array();

    foreach ($xactions as $key => $xaction) {
      $type = $xaction['type'];
      if (empty($types[$type])) {
        throw new Exception(
          pht(
            'Transaction with key "%s" has invalid type "%s". This type is '.
            'not recognized. Valid types are: %s.',
            $key,
            $type,
            implode(', ', array_keys($types))));
      }
    }

    if ($this->getIsCreate()) {
      $results[] = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_CREATE);
    }

    foreach ($xactions as $xaction) {
      $type = $types[$xaction['type']];

      // Let the parameter type interpret the value. This allows you to
      // use usernames in list<user> fields, for example.
      $parameter_type = $type->getConduitParameterType();

      $parameter_type->setViewer($viewer);

      try {
        $xaction['value'] = $parameter_type->getValue(
          $xaction,
          'value',
          $request->getIsStrictlyTyped());
      } catch (Exception $ex) {
        throw new PhutilProxyException(
          pht(
            'Exception when processing transaction of type "%s": %s',
            $xaction['type'],
            $ex->getMessage()),
          $ex);
      }

      $type_xactions = $type->generateTransactions(
        clone $template,
        $xaction);

      foreach ($type_xactions as $type_xaction) {
        $results[] = $type_xaction;
      }
    }

    return $results;
  }


  /**
   * @return map<string, PhabricatorEditType>
   * @task conduit
   */
  private function getConduitEditTypesFromFields(array $fields) {
    $types = array();
    foreach ($fields as $field) {
      $field_types = $field->getConduitEditTypes();

      if ($field_types === null) {
        continue;
      }

      foreach ($field_types as $field_type) {
        $field_type->setField($field);
        $types[$field_type->getEditType()] = $field_type;
      }
    }
    return $types;
  }

  public function getConduitEditTypes() {
    $config = $this->loadDefaultConfiguration();
    if (!$config) {
      return array();
    }

    $object = $this->newEditableObjectForDocumentation();
    $fields = $this->buildEditFields($object);
    return $this->getConduitEditTypesFromFields($fields);
  }

  final public static function getAllEditEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getEngineKey')
      ->execute();
  }

  final public static function getByKey(PhabricatorUser $viewer, $key) {
    return id(new PhabricatorEditEngineQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($key))
      ->executeOne();
  }

  public function getIcon() {
    $application = $this->getApplication();
    return $application->getIcon();
  }

  private function loadUsableConfigurationsForCreate() {
    $viewer = $this->getViewer();

    $configs = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($this->getEngineKey()))
      ->withIsDefault(true)
      ->withIsDisabled(false)
      ->execute();

    $configs = msort($configs, 'getCreateSortKey');

    // Attach this specific engine to configurations we load so they can access
    // any runtime configuration. For example, this allows us to generate the
    // correct "Create Form" buttons when editing forms, see T12301.
    foreach ($configs as $config) {
      $config->attachEngine($this);
    }

    return $configs;
  }

  protected function getValidationExceptionShortMessage(
    PhabricatorApplicationTransactionValidationException $ex,
    PhabricatorEditField $field) {

    $xaction_type = $field->getTransactionType();
    if ($xaction_type === null) {
      return null;
    }

    return $ex->getShortMessage($xaction_type);
  }

  protected function getCreateNewObjectPolicy() {
    return PhabricatorPolicies::POLICY_USER;
  }

  private function requireCreateCapability() {
    PhabricatorPolicyFilter::requireCapability(
      $this->getViewer(),
      $this,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  private function hasCreateCapability() {
    return PhabricatorPolicyFilter::hasCapability(
      $this->getViewer(),
      $this,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  public function isCommentAction() {
    return ($this->getEditAction() == 'comment');
  }

  public function getEditAction() {
    $controller = $this->getController();
    $request = $controller->getRequest();
    return $request->getURIData('editAction');
  }

  protected function newCommentActionGroups() {
    return array();
  }

  protected function newAutomaticCommentTransactions($object) {
    return array();
  }

  protected function newCommentPreviewContent($object, array $xactions) {
    return null;
  }


/* -(  Form Pages  )--------------------------------------------------------- */


  public function getSelectedPage() {
    return $this->page;
  }


  private function selectPage($object, $page_key) {
    $pages = $this->getPages($object);

    if (empty($pages[$page_key])) {
      return null;
    }

    $this->page = $pages[$page_key];
    return $this->page;
  }


  protected function newPages($object) {
    return array();
  }


  protected function getPages($object) {
    if ($this->pages === null) {
      $pages = $this->newPages($object);

      assert_instances_of($pages, 'PhabricatorEditPage');
      $pages = mpull($pages, null, 'getKey');

      $this->pages = $pages;
    }

    return $this->pages;
  }

  private function applyPageToFields($object, array $fields) {
    $pages = $this->getPages($object);
    if (!$pages) {
      return $fields;
    }

    if (!$this->getSelectedPage()) {
      return $fields;
    }

    $page_picks = array();
    $default_key = head($pages)->getKey();
    foreach ($pages as $page_key => $page) {
      foreach ($page->getFieldKeys() as $field_key) {
        $page_picks[$field_key] = $page_key;
      }
      if ($page->getIsDefault()) {
        $default_key = $page_key;
      }
    }

    $page_map = array_fill_keys(array_keys($pages), array());
    foreach ($fields as $field_key => $field) {
      if (isset($page_picks[$field_key])) {
        $page_map[$page_picks[$field_key]][$field_key] = $field;
        continue;
      }

      // TODO: Maybe let the field pick a page to associate itself with so
      // extensions can force themselves onto a particular page?

      $page_map[$default_key][$field_key] = $field;
    }

    $page = $this->getSelectedPage();
    if (!$page) {
      $page = head($pages);
    }

    $selected_key = $page->getKey();
    return $page_map[$selected_key];
  }

  protected function willApplyTransactions($object, array $xactions) {
    return $xactions;
  }

  protected function didApplyTransactions($object, array $xactions) {
    return;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getPHID() {
    return get_class($this);
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getCreateNewObjectPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
