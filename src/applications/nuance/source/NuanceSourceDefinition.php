<?php

/**
 * @task action Handling Action Requests
 */
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

  public function getSourceViewActions(AphrontRequest $request) {
    return array();
  }

  public static function getAllDefinitions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getSourceTypeConstant')
      ->execute();
  }

  /**
   * A human readable string like "Twitter" or "Phabricator Form".
   */
  abstract public function getName();


  /**
   * Human readable description of this source, a sentence or two long.
   */
  abstract public function getSourceDescription();

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
        ->setValue($source->getName()));

    $form = $this->augmentEditForm($form, $ex);

    $default_phid = $source->getDefaultQueuePHID();
    if ($default_phid) {
      $default_queues = array($default_phid);
    } else {
      $default_queues = array();
    }

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Default Queue'))
          ->setName('defaultQueuePHIDs')
          ->setLimit(1)
          ->setDatasource(new NuanceQueueDatasource())
          ->setValue($default_queues))
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

    $transactions[] = id(new NuanceSourceTransaction())
      ->setTransactionType(NuanceSourceTransaction::TYPE_DEFAULT_QUEUE)
      ->setNewvalue(head($request->getArr('defaultQueuePHIDs')));

    return $transactions;
  }

  abstract public function renderView();

  abstract public function renderListView();


  protected function newItemFromProperties(
    NuanceRequestor $requestor,
    array $properties,
    PhabricatorContentSource $content_source) {

    // TODO: Should we have a tighter actor/viewer model? Requestors will
    // often have no real user associated with them...
    $actor = PhabricatorUser::getOmnipotentUser();

    $source = $this->requireSourceObject();

    $item = NuanceItem::initializeNewItem();

    $xactions = array();

    $xactions[] = id(new NuanceItemTransaction())
      ->setTransactionType(NuanceItemTransaction::TYPE_SOURCE)
      ->setNewValue($source->getPHID());

    $xactions[] = id(new NuanceItemTransaction())
      ->setTransactionType(NuanceItemTransaction::TYPE_REQUESTOR)
      ->setNewValue($requestor->getPHID());

    // TODO: Eventually, apply real routing rules. For now, just put everything
    // in the default queue for the source.
    $xactions[] = id(new NuanceItemTransaction())
      ->setTransactionType(NuanceItemTransaction::TYPE_QUEUE)
      ->setNewValue($source->getDefaultQueuePHID());

    foreach ($properties as $key => $property) {
      $xactions[] = id(new NuanceItemTransaction())
        ->setTransactionType(NuanceItemTransaction::TYPE_PROPERTY)
        ->setMetadataValue(NuanceItemTransaction::PROPERTY_KEY, $key)
        ->setNewValue($property);
    }

    $editor = id(new NuanceItemEditor())
      ->setActor($actor)
      ->setActingAsPHID($requestor->getActingAsPHID())
      ->setContentSource($content_source);

    $editor->applyTransactions($item, $xactions);

    return $item;
  }

  public function renderItemViewProperties(
    PhabricatorUser $viewer,
    NuanceItem $item,
    PHUIPropertyListView $view) {
    return;
  }

  public function renderItemEditProperties(
    PhabricatorUser $viewer,
    NuanceItem $item,
    PHUIPropertyListView $view) {
    return;
  }


/* -(  Handling Action Requests  )------------------------------------------- */


  public function handleActionRequest(AphrontRequest $request) {
    return new Aphront404Response();
  }

  public function getActionURI($path = null) {
    $source_id = $this->getSourceObject()->getID();
    return '/action/'.$source_id.'/'.ltrim($path, '/');
  }

}
