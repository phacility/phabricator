<?php

abstract class PhabricatorQuickActions extends Phobject {

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function isEnabled() {
    return true;
  }

  abstract public function getQuickMenuItems();

  final public function getQuickActionsKey() {
    return $this->getPhobjectClassConstant('QUICKACTIONSKEY');
  }

  public static function getAllQuickActions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getQuickActionsKey')
      ->execute();
  }

  public static function loadMenuItemsForUser(PhabricatorUser $viewer) {
    $actions = self::getAllQuickActions();

    foreach ($actions as $key => $action) {
      $action->setViewer($viewer);
      if (!$action->isEnabled()) {
        unset($actions[$key]);
        continue;
      }
    }

    $items = array();
    foreach ($actions as $key => $action) {
      foreach ($action->getQuickMenuItems() as $item) {
        $items[] = $item;
      }
    }

    return $items;
  }

}
