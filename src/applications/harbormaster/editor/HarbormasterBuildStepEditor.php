<?php

final class HarbormasterBuildStepEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Harbormaster Build Steps');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = HarbormasterBuildStepTransaction::TYPE_CREATE;
    $types[] = HarbormasterBuildStepTransaction::TYPE_NAME;
    $types[] = HarbormasterBuildStepTransaction::TYPE_DEPENDS_ON;
    $types[] = HarbormasterBuildStepTransaction::TYPE_DESCRIPTION;

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
      case HarbormasterBuildStepTransaction::TYPE_DESCRIPTION:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getDescription();
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
      case HarbormasterBuildStepTransaction::TYPE_DESCRIPTION:
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
      case HarbormasterBuildStepTransaction::TYPE_DESCRIPTION:
        return $object->setDescription($xaction->getNewValue());
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
      case HarbormasterBuildStepTransaction::TYPE_DESCRIPTION:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
