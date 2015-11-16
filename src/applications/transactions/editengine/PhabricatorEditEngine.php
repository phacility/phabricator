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


/* -(  Managing Fields  )---------------------------------------------------- */


  abstract public function getEngineApplicationClass();
  abstract protected function buildCustomEditFields($object);

  final protected function buildEditFields($object) {
    $viewer = $this->getViewer();
    $editor = $object->getApplicationTransactionEditor();

    $types = $editor->getTransactionTypesForObject($object);
    $types = array_fuse($types);

    $fields = $this->buildCustomEditFields($object);

    if ($object instanceof PhabricatorPolicyInterface) {
      $policies = id(new PhabricatorPolicyQuery())
        ->setViewer($viewer)
        ->setObject($object)
        ->execute();

      $map = array(
        PhabricatorTransactions::TYPE_VIEW_POLICY => array(
          'key' => 'policy.view',
          'aliases' => array('view'),
          'capability' => PhabricatorPolicyCapability::CAN_VIEW,
          'label' => pht('View Policy'),
          'description' => pht('Controls who can view the object.'),
          'edit' => 'view',
        ),
        PhabricatorTransactions::TYPE_EDIT_POLICY => array(
          'key' => 'policy.edit',
          'aliases' => array('edit'),
          'capability' => PhabricatorPolicyCapability::CAN_EDIT,
          'label' => pht('Edit Policy'),
          'description' => pht('Controls who can edit the object.'),
          'edit' => 'edit',
        ),
        PhabricatorTransactions::TYPE_JOIN_POLICY => array(
          'key' => 'policy.join',
          'aliases' => array('join'),
          'capability' => PhabricatorPolicyCapability::CAN_JOIN,
          'label' => pht('Join Policy'),
          'description' => pht('Controls who can join the object.'),
          'edit' => 'join',
        ),
      );

      foreach ($map as $type => $spec) {
        if (empty($types[$type])) {
          continue;
        }

        $capability = $spec['capability'];
        $key = $spec['key'];
        $aliases = $spec['aliases'];
        $label = $spec['label'];
        $description = $spec['description'];
        $edit = $spec['edit'];

        $policy_field = id(new PhabricatorPolicyEditField())
          ->setKey($key)
          ->setLabel($label)
          ->setDescription($description)
          ->setAliases($aliases)
          ->setCapability($capability)
          ->setPolicies($policies)
          ->setTransactionType($type)
          ->setEditTypeKey($edit)
          ->setValue($object->getPolicy($capability));
        $fields[] = $policy_field;

        if ($object instanceof PhabricatorSpacesInterface) {
          if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
            $type_space = PhabricatorTransactions::TYPE_SPACE;
            if (isset($types[$type_space])) {
              $space_field = id(new PhabricatorSpaceEditField())
                ->setKey('spacePHID')
                ->setLabel(pht('Space'))
                ->setEditTypeKey('space')
                ->setDescription(
                  pht('Shifts the object in the Spaces application.'))
                ->setAliases(array('space', 'policy.space'))
                ->setTransactionType($type_space)
                ->setValue($object->getSpacePHID());
              $fields[] = $space_field;

              $policy_field->setSpaceField($space_field);
            }
          }
        }
      }
    }

    $edge_type = PhabricatorTransactions::TYPE_EDGE;
    $object_phid = $object->getPHID();

    $project_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;

    if ($object instanceof PhabricatorProjectInterface) {
      if (isset($types[$edge_type])) {
        if ($object_phid) {
          $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
            $object_phid,
            $project_edge_type);
          $project_phids = array_reverse($project_phids);
        } else {
          $project_phids = array();
        }

        $edge_field = id(new PhabricatorProjectsEditField())
          ->setKey('projectPHIDs')
          ->setLabel(pht('Projects'))
          ->setEditTypeKey('projects')
          ->setDescription(pht('Add or remove associated projects.'))
          ->setAliases(array('project', 'projects'))
          ->setTransactionType($edge_type)
          ->setMetadataValue('edge:type', $project_edge_type)
          ->setValue($project_phids);
        $fields[] = $edge_field;
      }
    }

    $subscribers_type = PhabricatorTransactions::TYPE_SUBSCRIBERS;

    if ($object instanceof PhabricatorSubscribableInterface) {
      if (isset($types[$subscribers_type])) {
        if ($object_phid) {
          $sub_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
            $object_phid);
        } else {
          // TODO: Allow applications to provide default subscribers; Maniphest
          // does this at a minimum.
          $sub_phids = array();
        }

        $subscribers_field = id(new PhabricatorSubscribersEditField())
          ->setKey('subscriberPHIDs')
          ->setLabel(pht('Subscribers'))
          ->setEditTypeKey('subscribers')
          ->setDescription(pht('Manage subscribers.'))
          ->setAliases(array('subscriber', 'subscribers'))
          ->setTransactionType($subscribers_type)
          ->setValue($sub_phids);
        $fields[] = $subscribers_field;
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
        $first->setBuiltinKey(self::EDITENGINECONFIG_DEFAULT);

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
  protected function getObjectEditURI($object) {
    return $this->getController()->getApplicationURI('edit/');
  }


  /**
   * @task uri
   */
  protected function getObjectCreateCancelURI($object) {
    return $this->getController()->getApplicationURI();
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
  protected function getEditURI($object, $path = null) {
    $parts = array(
      $this->getObjectEditURI($object),
    );

    if (!$this->getIsCreate()) {
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

    $config = $this->loadEditEngineConfiguration($request->getURIData('form'));
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
        $field->readValueFromSubmit($request);
      }

      $xactions = array();
      foreach ($fields as $field) {
        $xactions[] = $field->generateTransaction(clone $template);
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
      }
    } else {
      if ($this->getIsCreate()) {
        foreach ($fields as $field) {
          $field->readValueFromRequest($request);
        }
      } else {
        foreach ($fields as $field) {
          $field->readValueFromObject($object);
        }
      }
    }

    $action_button = $this->buildEditFormActionButton($object);

    if ($this->getIsCreate()) {
      $header_text = $this->getObjectCreateTitleText($object);
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
      ->addClass('pro-white-background')
      ->appendChild($document);
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

    $phid = $request->getValue('objectPHID');
    if ($phid) {
      $this->setIsCreate(false);
      $object = $this->newObjectFromPHID($phid);
      if (!$object) {
        throw new Exception(pht('No such object with PHID "%s".', $phid));
      }
    } else {
      $this->setIsCreate(true);
      $object = $this->newEditableObject();
    }

    $this->validateObject($object);

    $fields = $this->buildEditFields($object);

    $types = $this->getAllEditTypesFromFields($fields);
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

      $results[] = $type->generateTransaction(
        clone $template,
        $xaction);
    }

    return $results;
  }


  /**
   * @return map<string, PhabricatorEditType>
   * @task conduit
   */
  private function getAllEditTypesFromFields(array $fields) {
    $types = array();
    foreach ($fields as $field) {
      $field_types = $field->getEditTransactionTypes();
      foreach ($field_types as $field_type) {
        $field_type->setField($field);
        $types[$field_type->getEditType()] = $field_type;
      }
    }
    return $types;
  }

  public function getAllEditTypes() {
    $config = $this->loadEditEngineConfiguration(null);
    if (!$config) {
      return array();
    }

    $object = $this->newEditableObject();
    $fields = $this->buildEditFields($object);
    return $this->getAllEditTypesFromFields($fields);
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
