<?php

final class HeraldRuleEditTransaction
  extends HeraldRuleTransactionType {

  const TRANSACTIONTYPE = 'herald:edit';

  public function generateOldValue($object) {
    return id(new HeraldRuleSerializer())
      ->serializeRule($object);
  }

  public function applyInternalEffects($object, $value) {
    $new_state = id(new HeraldRuleSerializer())
      ->deserializeRuleComponents($value);

    $object->setMustMatchAll((int)$new_state['match_all']);
    $object->attachConditions($new_state['conditions']);
    $object->attachActions($new_state['actions']);

    $new_repetition = $new_state['repetition_policy'];
    $object->setRepetitionPolicyStringConstant($new_repetition);
  }

  public function applyExternalEffects($object, $value) {
    $object->saveConditions($object->getConditions());
    $object->saveActions($object->getActions());
  }

  public function getTitle() {
    return pht(
      '%s edited this rule.',
      $this->renderAuthor());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionJSONDiffDetailView())
      ->setViewer($viewer)
      ->setOld($this->getOldValue())
      ->setNew($this->getNewValue());
  }

}
