<?php

/**
 * @task action Handling Action Requests
 */
abstract class NuanceSourceDefinition extends Phobject {

  private $viewer;
  private $source;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    if (!$this->viewer) {
      throw new PhutilInvalidStateException('setViewer');
    }
    return $this->viewer;
  }

  public function setSource(NuanceSource $source) {
    $this->source = $source;
    return $this;
  }

  public function getSource() {
    if (!$this->source) {
      throw new PhutilInvalidStateException('setSource');
    }
    return $this->source;
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

  public function hasImportCursors() {
    return false;
  }

  final public function getImportCursors() {
    if (!$this->hasImportCursors()) {
      throw new Exception(
        pht('This source has no input cursors.'));
    }

    return $this->newImportCursors();
  }

  protected function newImportCursors() {
    throw new PhutilMethodNotImplementedException();
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

  abstract public function renderView();

  abstract public function renderListView();


  protected function newItemFromProperties(
    NuanceRequestor $requestor,
    array $properties,
    PhabricatorContentSource $content_source) {

    // TODO: Should we have a tighter actor/viewer model? Requestors will
    // often have no real user associated with them...
    $actor = PhabricatorUser::getOmnipotentUser();
    $source = $this->getSource();

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
    $source_id = $this->getSource()->getID();
    return '/action/'.$source_id.'/'.ltrim($path, '/');
  }

}
