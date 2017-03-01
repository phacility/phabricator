<?php

// TODO: Some "final" modifiers have been VERY TEMPORARILY moved aside to
// allow DifferentialTransaction to extend this class without converting
// fully to ModularTransactions.

abstract class PhabricatorModularTransaction
  extends PhabricatorApplicationTransaction {

  private $implementation;

  abstract public function getBaseTransactionClass();

  public function getModularType() {
    return $this->getTransactionImplementation();
  }

  final protected function getTransactionImplementation() {
    if (!$this->implementation) {
      $this->implementation = $this->newTransactionImplementation();
    }

    return $this->implementation;
  }

  public function newModularTransactionTypes() {
    $base_class = $this->getBaseTransactionClass();

    $types = id(new PhutilClassMapQuery())
      ->setAncestorClass($base_class)
      ->setUniqueMethod('getTransactionTypeConstant')
      ->execute();

    // Add core transaction types.
    $types += id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorCoreTransactionType')
      ->setUniqueMethod('getTransactionTypeConstant')
      ->execute();

    return $types;
  }

  private function newTransactionImplementation() {
    $types = $this->newModularTransactionTypes();

    $key = $this->getTransactionType();

    if (empty($types[$key])) {
      $type = $this->newFallbackModularTransactionType();
    } else {
      $type = clone $types[$key];
    }

    $type->setStorage($this);

    return $type;
  }

  protected function newFallbackModularTransactionType() {
    return new PhabricatorCoreVoidTransaction();
  }

  final public function generateOldValue($object) {
    return $this->getTransactionImplementation()->generateOldValue($object);
  }

  final public function generateNewValue($object) {
    return $this->getTransactionImplementation()
      ->generateNewValue($object, $this->getNewValue());
  }

  final public function willApplyTransactions($object, array $xactions) {
    return $this->getTransactionImplementation()
      ->willApplyTransactions($object, $xactions);
  }

  final public function applyInternalEffects($object) {
    return $this->getTransactionImplementation()
      ->applyInternalEffects($object);
  }

  final public function applyExternalEffects($object) {
    return $this->getTransactionImplementation()
      ->applyExternalEffects($object);
  }

  /* final */ public function shouldHide() {
    if ($this->getTransactionImplementation()->shouldHide()) {
      return true;
    }

    return parent::shouldHide();
  }

  /* final */ public function getIcon() {
    $icon = $this->getTransactionImplementation()->getIcon();
    if ($icon !== null) {
      return $icon;
    }

    return parent::getIcon();
  }

  /* final */ public function getTitle() {
    $title = $this->getTransactionImplementation()->getTitle();
    if ($title !== null) {
      return $title;
    }

    return parent::getTitle();
  }

  /* final */ public function getActionName() {
    $action = $this->getTransactionImplementation()->getActionName();
    if ($action !== null) {
      return $action;
    }

    return parent::getActionName();
  }

  /* final */ public function getActionStrength() {
    $strength = $this->getTransactionImplementation()->getActionStrength();
    if ($strength !== null) {
      return $strength;
    }

    return parent::getActionStrength();
  }

  public function getTitleForMail() {
    $old_target = $this->getRenderingTarget();
    $new_target = self::TARGET_TEXT;
    $this->setRenderingTarget($new_target);
    $title = $this->getTitle();
    $this->setRenderingTarget($old_target);
    return $title;
  }

  /* final */ public function getTitleForFeed() {
    $title = $this->getTransactionImplementation()->getTitleForFeed();
    if ($title !== null) {
      return $title;
    }

    return parent::getTitleForFeed();
  }

  /* final */ public function getColor() {
    $color = $this->getTransactionImplementation()->getColor();
    if ($color !== null) {
      return $color;
    }

    return parent::getColor();
  }

  public function attachViewer(PhabricatorUser $viewer) {
    $this->getTransactionImplementation()->setViewer($viewer);
    return parent::attachViewer($viewer);
  }

  final public function hasChangeDetails() {
    if ($this->getTransactionImplementation()->hasChangeDetailView()) {
      return true;
    }

    return parent::hasChangeDetails();
  }

  final public function renderChangeDetails(PhabricatorUser $viewer) {
    $impl = $this->getTransactionImplementation();
    $impl->setViewer($viewer);
    $view = $impl->newChangeDetailView();
    if ($view !== null) {
      return $view;
    }

    return parent::renderChangeDetails($viewer);
  }

  final protected function newRemarkupChanges() {
    return $this->getTransactionImplementation()->newRemarkupChanges();
  }

}
