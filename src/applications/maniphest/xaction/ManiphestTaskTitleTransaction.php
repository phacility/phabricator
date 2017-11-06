<?php

final class ManiphestTaskTitleTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'title';

  public function generateOldValue($object) {
    return $object->getTitle();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTitle($value);
  }

  public function getActionStrength() {
    return 1.4;
  }

  public function getActionName() {
    $old = $this->getOldValue();

    if (!strlen($old)) {
      return pht('Created');
    }

    return pht('Retitled');
  }

  public function getTitle() {
    $old = $this->getOldValue();

    if (!strlen($old)) {
      return pht(
        '%s created this task.',
        $this->renderAuthor());
    }

    return pht(
      '%s renamed this task from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());

  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    if ($old === null) {
      return pht(
        '%s created %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }

    return pht(
      '%s renamed %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getTitle(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Tasks must have a title.'));
    }

    return $errors;
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'title';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'old' => $xaction->getOldValue(),
      'new' => $xaction->getNewValue(),
    );
  }

}
