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

  private $viewer;
  private $controller;
  private $isCreate;
  private $editEngineConfiguration;

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
    return $this->getPhobjectClassConstant('ENGINECONST', 64);
  }

  final public function getApplication() {
    $app_class = $this->getEngineApplicationClass();
    return PhabricatorApplication::getByClass($app_class);
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

    $extensions = PhabricatorEditEngineExtension::getAllEnabledExtensions();
    foreach ($extensions as $extension) {
      $extension->setViewer($viewer);

      if (!$extension->supportsObject($this, $object)) {
        continue;
      }

      $extension_fields = $extension->buildCustomEditFields($this, $object);

      // TODO: Validate this in more detail with a more tailored error.
      assert_instances_of($extension_fields, 'PhabricatorEditField');

      foreach ($extension_fields as $field) {
        $fields[] = $field;
      }
    }

    $config = $this->getEditEngineConfiguration();
    $fields = $config->applyConfigurationToFields($this, $fields);

    foreach ($fields as $field) {
      $field
        ->setViewer($viewer)
        ->setObject($object);
    }

    return $fields;
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
  protected function getCommentViewHeaderText($object) {
    return pht('Add Comment');
  }


  /**
   * @task text
   */
  protected function getCommentViewButtonText($object) {
    return pht('Add Comment');
  }


/* -(  Edit Engine Configuration  )------------------------------------------ */


  protected function supportsEditEngineConfiguration() {
    return true;
  }

  final protected function getEditEngineConfiguration() {
    return $this->editEngineConfiguration;
  }

  private function loadEditEngineConfiguration($key) {
    if ($key === null) {
      $key = self::EDITENGINECONFIG_DEFAULT;
    }

    $config = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($this->getViewer())
      ->withEngineKeys(array($this->getEngineKey()))
      ->withIdentifiers(array($key))
      ->executeOne();
    if (!$config) {
      return null;
    }

    $this->editEngineConfiguration = $config;

    return $config;
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
          ->setIsDefault(true);

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
   * @return object Corresponding editable object.
   * @task load
   */
  private function newObjectFromIdentifier($identifier) {
    if (is_int($identifier) || ctype_digit($identifier)) {
      $object = $this->newObjectFromID($identifier);

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
      $object = $this->newObjectFromPHID($identifier);

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

    $object = $this->newObjectFromPHID($target->getPHID());
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
   * @return object|null Object, or null if no such object exists.
   * @task load
   */
  private function newObjectFromID($id) {
    $query = $this->newObjectQuery()
      ->withIDs(array($id));

    return $this->newObjectFromQuery($query);
  }


  /**
   * Load an object by PHID.
   *
   * @param phid Object PHID.
   * @return object|null Object, or null if no such object exists.
   * @task load
   */
  private function newObjectFromPHID($phid) {
    $query = $this->newObjectQuery()
      ->withPHIDs(array($phid));

    return $this->newObjectFromQuery($query);
  }


  /**
   * Load an object given a configured query.
   *
   * @param PhabricatorPolicyAwareQuery Configured query.
   * @return object|null Object, or null if no such object exists.
   * @task load
   */
  private function newObjectFromQuery(PhabricatorPolicyAwareQuery $query) {
    $viewer = $this->getViewer();

    $object = $query
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
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

    $form_key = $request->getURIData('formKey');
    $config = $this->loadEditEngineConfiguration($form_key);
    if (!$config) {
      return new Aphront404Response();
    }

    $id = $request->getURIData('id');
    if ($id) {
      $this->setIsCreate(false);
      $object = $this->newObjectFromID($id);
      if (!$object) {
        return new Aphront404Response();
      }
    } else {
      $this->setIsCreate(true);
      $object = $this->newEditableObject();
    }

    $this->validateObject($object);

    $action = $request->getURIData('editAction');
    switch ($action) {
      case 'parameters':
        return $this->buildParametersResponse($object);
      case 'nodefault':
        return $this->buildNoDefaultResponse($object);
      case 'comment':
        return $this->buildCommentResponse($object);
      default:
        return $this->buildEditResponse($object);
    }
  }

  private function buildCrumbs($object, $final = false) {
    $controller = $this->getcontroller();

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
        $this->getObjectViewURI($object));

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

    $validation_exception = null;
    if ($request->isFormPost()) {
      foreach ($fields as $field) {
        $field->setIsSubmittedForm(true);

        if ($field->getIsLocked() || $field->getIsHidden()) {
          continue;
        }

        $field->readValueFromSubmit($request);
      }

      $xactions = array();
      foreach ($fields as $field) {
        $types = $field->getWebEditTypes();
        foreach ($types as $type) {
          $type_xactions = $type->generateTransactions(
            clone $template,
            array(
              'value' => $field->getValueForTransaction(),
            ));

          if (!$type_xactions) {
            continue;
          }

          foreach ($type_xactions as $type_xaction) {
            $xactions[] = $type_xaction;
          }
        }
      }

      $editor = $object->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {

        $editor->applyTransactions($object, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI($this->getObjectViewURI($object));
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        foreach ($fields as $field) {
          $xaction_type = $field->getTransactionType();
          if ($xaction_type === null) {
            continue;
          }

          $message = $ex->getShortMessage($xaction_type);
          if ($message === null) {
            continue;
          }

          $field->setControlError($message);
        }
      }
    } else {
      if ($this->getIsCreate()) {
        foreach ($fields as $field) {
          if ($field->getIsLocked() || $field->getIsHidden()) {
            continue;
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

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text)
      ->addActionLink($action_button);

    $crumbs = $this->buildCrumbs($object, $final = true);
    $form = $this->buildEditForm($object, $fields);

    $box = id(new PHUIObjectBoxView())
      ->setUser($viewer)
      ->setHeader($header)
      ->setValidationException($validation_exception)
      ->appendChild($form);

    return $controller->newPage()
      ->setTitle($header_text)
      ->setCrumbs($crumbs)
      ->appendChild($box);
  }

  private function buildEditForm($object, array $fields) {
    $viewer = $this->getViewer();

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    foreach ($fields as $field) {
      $field->appendToForm($form);
    }

    if ($this->getIsCreate()) {
      $cancel_uri = $this->getObjectCreateCancelURI($object);
      $submit_button = $this->getObjectCreateButtonText($object);
    } else {
      $cancel_uri = $this->getObjectEditCancelURI($object);
      $submit_button = $this->getObjectEditButtonText($object);
    }

    $form->appendControl(
      id(new AphrontFormSubmitControl())
        ->addCancelButton($cancel_uri)
        ->setValue($submit_button));

    return $form;
  }

  private function buildEditFormActionButton($object) {
    $viewer = $this->getViewer();

    $action_view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    foreach ($this->buildEditFormActions($object) as $action) {
      $action_view->addAction($action);
    }

    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Actions'))
      ->setHref('#')
      ->setIconFont('fa-bars')
      ->setDropdownMenu($action_view);

    return $action_button;
  }

  private function buildEditFormActions($object) {
    $actions = array();

    if ($this->supportsEditEngineConfiguration()) {
      $engine_key = $this->getEngineKey();
      $config = $this->getEditEngineConfiguration();

      $actions[] = id(new PhabricatorActionView())
        ->setName(pht('Manage Form Configurations'))
        ->setIcon('fa-list-ul')
        ->setHref("/transactions/editengine/{$engine_key}/");
      $actions[] = id(new PhabricatorActionView())
        ->setName(pht('Edit Form Configuration'))
        ->setIcon('fa-pencil')
        ->setHref($config->getURI());
    }

    $actions[] = id(new PhabricatorActionView())
      ->setName(pht('Show HTTP Parameters'))
      ->setIcon('fa-crosshairs')
      ->setHref($this->getEditURI($object, 'parameters/'));

    return $actions;
  }

  final public function addActionToCrumbs(PHUICrumbsView $crumbs) {
    $viewer = $this->getViewer();

    $configs = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($this->getEngineKey()))
      ->withIsDefault(true)
      ->withIsDisabled(false)
      ->execute();

    $dropdown = null;
    $disabled = false;
    $workflow = false;

    $menu_icon = 'fa-plus-square';

    if (!$configs) {
      if ($viewer->isLoggedIn()) {
        $disabled = true;
      } else {
        // If the viewer isn't logged in, assume they'll get hit with a login
        // dialog and are likely able to create objects after they log in.
        $disabled = false;
      }
      $workflow = true;
      $create_uri = $this->getEditURI(null, 'nodefault/');
    } else {
      $config = head($configs);
      $form_key = $config->getIdentifier();
      $create_uri = $this->getEditURI(null, "form/{$form_key}/");

      if (count($configs) > 1) {
        $configs = msort($configs, 'getDisplayName');

        $menu_icon = 'fa-caret-square-o-down';

        $dropdown = id(new PhabricatorActionListView())
          ->setUser($viewer);

        foreach ($configs as $config) {
          $form_key = $config->getIdentifier();
          $config_uri = $this->getEditURI(null, "form/{$form_key}/");

          $item_icon = 'fa-plus';

          $dropdown->addAction(
            id(new PhabricatorActionView())
              ->setName($config->getDisplayName())
              ->setIcon($item_icon)
              ->setHref($config_uri));
        }
      }
    }

    $action = id(new PHUIListItemView())
      ->setName($this->getObjectCreateShortText())
      ->setHref($create_uri)
      ->setIcon($menu_icon)
      ->setWorkflow($workflow)
      ->setDisabled($disabled);

    if ($dropdown) {
      $action->setDropdownMenu($dropdown);
    }

    $crumbs->addAction($action);
  }

  final public function buildEditEngineCommentView($object) {
    $config = $this->loadEditEngineConfiguration(null);

    $viewer = $this->getViewer();
    $object_phid = $object->getPHID();

    $header_text = $this->getCommentViewHeaderText($object);
    $button_text = $this->getCommentViewButtonText($object);

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

    $all_types = array();
    foreach ($fields as $field) {
      // TODO: Load draft stuff.
      $types = $field->getCommentEditTypes();
      foreach ($types as $type) {
        $all_types[] = $type;
      }
    }

    $view->setEditTypes($all_types);

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


  private function buildNoDefaultResponse($object) {
    $cancel_uri = $this->getObjectCreateCancelURI($object);

    return $this->getController()
      ->newDialog()
      ->setTitle(pht('No Default Create Forms'))
      ->appendParagraph(
        pht(
          'This application is not configured with any visible, enabled '.
          'forms for creating objects.'))
      ->addCancelButton($cancel_uri);
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

    $config = $this->loadEditEngineConfiguration(null);
    $fields = $this->buildEditFields($object);

    $is_preview = $request->isPreviewRequest();
    $view_uri = $this->getObjectViewURI($object);

    $template = $object->getApplicationTransactionTemplate();
    $comment_template = $template->getApplicationTransactionCommentObject();

    $comment_text = $request->getStr('comment');

    if ($is_preview) {
      $version_key = PhabricatorVersionedDraft::KEY_VERSION;
      $request_version = $request->getInt($version_key);
      $current_version = $this->loadDraftVersion($object);
      if ($request_version >= $current_version) {
        $draft = PhabricatorVersionedDraft::loadOrCreateDraft(
          $object->getPHID(),
          $viewer->getPHID(),
          $current_version);

        // TODO: This is just a proof of concept.
        $draft->setProperty('temporary.comment', $comment_text);
        $draft->save();
      }
    }

    $xactions = array();

    $actions = $request->getStr('editengine.actions');
    if ($actions) {
      $type_map = array();
      foreach ($fields as $field) {
        $types = $field->getCommentEditTypes();
        foreach ($types as $type) {
          $type_map[$type->getEditType()] = $type;
        }
      }

      $actions = phutil_json_decode($actions);
      foreach ($actions as $action) {
        $type = idx($action, 'type');
        if (!$type) {
          continue;
        }

        $edit_type = idx($type_map, $type);
        if (!$edit_type) {
          continue;
        }

        $type_xactions = $edit_type->generateTransactions(
          $template,
          array(
            'value' => idx($action, 'value'),
          ));
        foreach ($type_xactions as $type_xaction) {
          $xactions[] = $type_xaction;
        }
      }
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
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($object, $xactions);
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
    }

    if ($request->isAjax() && $is_preview) {
      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($viewer)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview);
    } else {
      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }
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

    $config = $this->loadEditEngineConfiguration(null);
    if (!$config) {
      throw new Exception(
        pht(
          'Unable to load configuration for this EditEngine ("%s").',
          get_class($this)));
    }

    $identifier = $request->getValue('objectIdentifier');
    if ($identifier) {
      $this->setIsCreate(false);
      $object = $this->newObjectFromIdentifier($identifier);
    } else {
      $this->setIsCreate(true);
      $object = $this->newEditableObject();
    }

    $this->validateObject($object);

    $fields = $this->buildEditFields($object);

    $types = $this->getConduitEditTypesFromFields($fields);
    $template = $object->getApplicationTransactionTemplate();

    $xactions = $this->getConduitTransactions($request, $types, $template);

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setContentSourceFromConduitRequest($request)
      ->setContinueOnNoEffect(true);

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


  /**
   * Generate transactions which can be applied from edit actions in a Conduit
   * request.
   *
   * @param ConduitAPIRequest The request.
   * @param list<PhabricatorEditType> Supported edit types.
   * @param PhabricatorApplicationTransaction Template transaction.
   * @return list<PhabricatorApplicationTransaction> Generated transactions.
   * @task conduit
   */
  private function getConduitTransactions(
    ConduitAPIRequest $request,
    array $types,
    PhabricatorApplicationTransaction $template) {

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

    $results = array();
    foreach ($xactions as $xaction) {
      $type = $types[$xaction['type']];

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
    $config = $this->loadEditEngineConfiguration(null);
    if (!$config) {
      return array();
    }

    $object = $this->newEditableObject();
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


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getPHID() {
    return get_class($this);
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }
}
