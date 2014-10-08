<?php

final class HarbormasterBuildableTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Harbormaster Buildables');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = HarbormasterBuildableTransaction::TYPE_CREATE;
    $types[] = HarbormasterBuildableTransaction::TYPE_COMMAND;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildableTransaction::TYPE_CREATE:
      case HarbormasterBuildableTransaction::TYPE_COMMAND:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildableTransaction::TYPE_CREATE:
        return true;
      case HarbormasterBuildableTransaction::TYPE_COMMAND:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildableTransaction::TYPE_CREATE:
      case HarbormasterBuildableTransaction::TYPE_COMMAND:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildableTransaction::TYPE_CREATE:
      case HarbormasterBuildableTransaction::TYPE_COMMAND:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
