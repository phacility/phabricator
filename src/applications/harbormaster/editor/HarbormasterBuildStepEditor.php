<?php

final class HarbormasterBuildStepEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = HarbormasterBuildStepTransaction::TYPE_CREATE;
    $types[] = HarbormasterBuildStepTransaction::TYPE_NAME;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildStepTransaction::TYPE_CREATE:
        return null;
      case HarbormasterBuildStepTransaction::TYPE_NAME:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getName();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildStepTransaction::TYPE_CREATE:
        return true;
      case HarbormasterBuildStepTransaction::TYPE_NAME:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildStepTransaction::TYPE_CREATE:
        return;
      case HarbormasterBuildStepTransaction::TYPE_NAME:
        return $object->setName($xaction->getNewValue());
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildStepTransaction::TYPE_CREATE:
      case HarbormasterBuildStepTransaction::TYPE_NAME:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
