<?php

abstract class NuanceSourceDefinition extends Phobject {

  private $actor;
  private $sourceObject;

  public function setActor(PhabricatorUser $actor) {
    $this->actor = $actor;
    return $this;
  }
  public function getActor() {
    return $this->actor;
  }
  public function requireActor() {
    $actor = $this->getActor();
    if (!$actor) {
      throw new PhutilInvalidStateException('setActor');
    }
    return $actor;
  }

  public function setSourceObject(NuanceSource $source) {
    $source->setType($this->getSourceTypeConstant());
    $this->sourceObject = $source;
    return $this;
  }
  public function getSourceObject() {
    return $this->sourceObject;
  }
  public function requireSourceObject() {
    $source = $this->getSourceObject();
    if (!$source) {
      throw new PhutilInvalidStateException('setSourceObject');
    }
    return $source;
  }

  public static function getSelectOptions() {
    $definitions = self::getAllDefinitions();

    $options = array();
    foreach ($definitions as $definition) {
      $key = $definition->getSourceTypeConstant();
      $name = $definition->getName();
      $options[$key] = $name;
    }

    return $options;
  }

  /**
   * Gives a @{class:NuanceSourceDefinition} object for a given
   * @{class:NuanceSource}. Note you still need to @{method:setActor}
   * before the @{class:NuanceSourceDefinition} object will be useful.
   */
  public static function getDefinitionForSource(NuanceSource $source) {
    $definitions = self::getAllDefinitions();
    $map = mpull($definitions, null, 'getSourceTypeConstant');
    $definition = $map[$source->getType()];
    $definition->setSourceObject($source);

    return $definition;
  }

  public static function getAllDefinitions() {
    static $definitions;

    if ($definitions === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();
      foreach ($objects as $definition) {
        $key = $definition->getSourceTypeConstant();
        $name = $definition->getName();
        if (isset($definitions[$key])) {
          $conflict = $definitions[$key];
          throw new Exception(
            pht(
              'Definition %s conflicts with definition %s. This is a '.
              'programming error.',
              $conflict,
              $name));
        }
      }
      $definitions = $objects;
    }
    return $definitions;
  }

  /**
   * A human readable string like "Twitter" or "Phabricator Form".
   */
  abstract public function getName();

  /**
   * This should be a any VARCHAR(32).
   *
   * @{method:getAllDefinitions} will throw if you choose a string that
   * collides with another @{class:NuanceSourceDefinition} class.
   */
  abstract public function getSourceTypeConstant();

  /**
   * Code to create and update @{class:NuanceItem}s and
   * @{class:NuanceRequestor}s via daemons goes here.
   *
   * If that does not make sense for the @{class:NuanceSource} you are
   * defining, simply return null. For example,
   * @{class:NuancePhabricatorFormSourceDefinition} since these are one-way
   * contact forms.
   */
  abstract public function updateItems();

  private function loadSourceObjectPolicies(
    PhabricatorUser $user,
    NuanceSource $source) {

    $user = $this->requireActor();
    $source = $this->requireSourceObject();
    return id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($source)
      ->execute();
  }

  final public function getEditTitle() {
    $source = $this->requireSourceObject();
    if ($source->getPHID()) {
      $title = pht('Edit "%s" source.', $source->getName());
    } else {
      $title = pht('Create a new "%s" source.', $this->getName());
    }

    return $title;
  }

  final public function buildEditLayout(AphrontRequest $request) {
    $actor = $this->requireActor();
    $source = $this->requireSourceObject();

    $form_errors = array();
    $error_messages = array();
    $transactions = array();
    $validation_exception = null;
    if ($request->isFormPost()) {
      $transactions = $this->buildTransactions($request);
      try {
        $editor = id(new NuanceSourceEditor())
          ->setActor($actor)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->applyTransactions($source, $transactions);

        return id(new AphrontRedirectResponse())
          ->setURI($source->getURI());

      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }

    }

    $form = $this->renderEditForm($validation_exception);
    $layout = id(new PHUIObjectBoxView())
      ->setHeaderText($this->getEditTitle())
      ->setValidationException($validation_exception)
      ->setFormErrors($error_messages)
      ->setForm($form);

    return $layout;
  }

  /**
   * Code to create a form to edit the @{class:NuanceItem} you are defining.
   *
   * return @{class:AphrontFormView}
   */
  private function renderEditForm(
    PhabricatorApplicationTransactionValidationException $ex = null) {
    $user = $this->requireActor();
    $source = $this->requireSourceObject();
    $policies = $this->loadSourceObjectPolicies($user, $source);
    $e_name = null;
    if ($ex) {
      $e_name = $ex->getShortMessage(NuanceSourceTransaction::TYPE_NAME);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Name'))
        ->setName('name')
        ->setError($e_name)
        ->setValue($source->getName()))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel(pht('Type'))
        ->setName('type')
        ->setOptions(self::getSelectOptions())
        ->setValue($source->getType()));

    $form = $this->augmentEditForm($form, $ex);

    $form
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($user)
        ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
        ->setPolicyObject($source)
        ->setPolicies($policies)
        ->setName('viewPolicy'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($user)
        ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
        ->setPolicyObject($source)
        ->setPolicies($policies)
        ->setName('editPolicy'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->addCancelButton($source->getURI())
        ->setValue(pht('Save')));

    return $form;
  }

  /**
   * return @{class:AphrontFormView}
   */
  protected function augmentEditForm(
    AphrontFormView $form,
    PhabricatorApplicationTransactionValidationException $ex = null) {

    return $form;
  }

  /**
   * Hook to build up @{class:PhabricatorTransactions}.
   *
   * return array $transactions
   */
  protected function buildTransactions(AphrontRequest $request) {
    $transactions = array();

    $transactions[] = id(new NuanceSourceTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
      ->setNewValue($request->getStr('editPolicy'));
    $transactions[] = id(new NuanceSourceTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
      ->setNewValue($request->getStr('viewPolicy'));
   $transactions[] = id(new NuanceSourceTransaction())
      ->setTransactionType(NuanceSourceTransaction::TYPE_NAME)
      ->setNewvalue($request->getStr('name'));

    return $transactions;
  }

  abstract public function renderView();

  abstract public function renderListView();
}
