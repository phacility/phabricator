<?php

final class PhabricatorProjectTriggerRulesetTransaction
  extends PhabricatorProjectTriggerTransactionType {

  const TRANSACTIONTYPE = 'ruleset';

  public function generateOldValue($object) {
    return $object->getRuleset();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRuleset($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the ruleset for this trigger.',
      $this->renderAuthor());
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    foreach ($xactions as $xaction) {
      $ruleset = $xaction->getNewValue();

      try {
        $rules =
          PhabricatorProjectTrigger::newTriggerRulesFromRuleSpecifications(
            $ruleset,
            $allow_invalid = false,
            $actor);
      } catch (PhabricatorProjectTriggerCorruptionException $ex) {
        $errors[] = $this->newInvalidError(
          pht(
            'Ruleset specification is not valid. %s',
            $ex->getMessage()),
          $xaction);
        continue;
      }

      foreach ($rules as $rule) {
        $exception = $rule->getRuleRecordValueValidationException();
        if ($exception) {
          $errors[] = $this->newInvalidError(
            pht(
              'Value for "%s" rule is invalid: %s',
              $rule->getSelectControlName(),
              $exception->getMessage()),
            $xaction);
          continue;
        }
      }
    }

    return $errors;
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $json = new PhutilJSON();
    $old_json = $json->encodeAsList($old);
    $new_json = $json->encodeAsList($new);

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($old_json)
      ->setNewText($new_json);
  }

}
