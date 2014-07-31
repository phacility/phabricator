<?php

final class HarbormasterBuildStepEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = HarbormasterBuildStepTransaction::TYPE_CREATE;
    $types[] = HarbormasterBuildStepTransaction::TYPE_NAME;
    $types[] = HarbormasterBuildStepTransaction::TYPE_DEPENDS_ON;

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
      case HarbormasterBuildStepTransaction::TYPE_DEPENDS_ON:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getDetail('dependsOn', array());
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
      case HarbormasterBuildStepTransaction::TYPE_DEPENDS_ON:
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
      case HarbormasterBuildStepTransaction::TYPE_DEPENDS_ON:
        return $object->setDetail('dependsOn', $xaction->getNewValue());
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildStepTransaction::TYPE_CREATE:
      case HarbormasterBuildStepTransaction::TYPE_NAME:
      case HarbormasterBuildStepTransaction::TYPE_DEPENDS_ON:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
