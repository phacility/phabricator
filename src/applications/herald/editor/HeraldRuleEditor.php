<?php

final class HeraldRuleEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Herald Rules');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = HeraldRuleTransaction::TYPE_EDIT;
    $types[] = HeraldRuleTransaction::TYPE_NAME;
    $types[] = HeraldRuleTransaction::TYPE_DISABLE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HeraldRuleTransaction::TYPE_DISABLE:
        return (int)$object->getIsDisabled();
      case HeraldRuleTransaction::TYPE_EDIT:
        return id(new HeraldRuleSerializer())
          ->serializeRule($object);
      case HeraldRuleTransaction::TYPE_NAME:
        return $object->getName();
    }

  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HeraldRuleTransaction::TYPE_DISABLE:
        return (int)$xaction->getNewValue();
      case HeraldRuleTransaction::TYPE_EDIT:
      case HeraldRuleTransaction::TYPE_NAME:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HeraldRuleTransaction::TYPE_DISABLE:
        return $object->setIsDisabled($xaction->getNewValue());
      case HeraldRuleTransaction::TYPE_NAME:
        return $object->setName($xaction->getNewValue());
      case HeraldRuleTransaction::TYPE_EDIT:
        $new_state = id(new HeraldRuleSerializer())
          ->deserializeRuleComponents($xaction->getNewValue());
        $object->setMustMatchAll((int)$new_state['match_all']);
        $object->attachConditions($new_state['conditions']);
        $object->attachActions($new_state['actions']);
        $object->setRepetitionPolicy(
          HeraldRepetitionPolicyConfig::toInt($new_state['repetition_policy']));
        return $object;
    }

  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case HeraldRuleTransaction::TYPE_EDIT:
        $object->saveConditions($object->getConditions());
        $object->saveActions($object->getActions());
        break;
    }
    return;
  }

}
