<?php

abstract class NuanceItemType
  extends Phobject {

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
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

}
