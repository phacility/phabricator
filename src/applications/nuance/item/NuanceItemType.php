<?php

abstract class NuanceItemType
  extends Phobject {

  private $viewer;
  private $controller;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  public function getController() {
    return $this->controller;
  }

  public function canUpdateItems() {
    return false;
  }

  final public function buildItemView(NuanceItem $item) {
    return $this->newItemView($item);
  }

  protected function newItemView() {
    return null;
  }

  public function getItemTypeDisplayIcon() {
    return null;
  }

  public function getItemActions(NuanceItem $item) {
    return array();
  }

  abstract public function getItemTypeDisplayName();
  abstract public function getItemDisplayName(NuanceItem $item);

  final public function updateItem(NuanceItem $item) {
    if (!$this->canUpdateItems()) {
      throw new Exception(
        pht(
          'This item type ("%s", of class "%s") can not update items.',
          $this->getItemTypeConstant(),
          get_class($this)));
    }

    $this->updateItemFromSource($item);
  }

  protected function updateItemFromSource(NuanceItem $item) {
    throw new PhutilMethodNotImplementedException();
  }

  final public function getItemTypeConstant() {
    return $this->getPhobjectClassConstant('ITEMTYPE', 64);
  }

  final public static function getAllItemTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getItemTypeConstant')
      ->execute();
  }

  final protected function newItemAction(NuanceItem $item, $key) {
    $id = $item->getID();
    $action_uri = "/nuance/item/action/{$id}/{$key}/";

    return id(new PhabricatorActionView())
      ->setHref($action_uri);
  }

  final public function buildActionResponse(NuanceItem $item, $action) {
    $response = $this->handleAction($item, $action);

    if ($response === null) {
      return new Aphront404Response();
    }

    return $response;
  }

  protected function handleAction(NuanceItem $item, $action) {
    return null;
  }

}
