<?php

final class HarbormasterBuildPlanBehaviorTransaction
  extends HarbormasterBuildPlanTransactionType {

  const TRANSACTIONTYPE = 'behavior';

  public function generateOldValue($object) {
    $behavior = $this->getBehavior();
    return $behavior->getPlanOption($object)->getKey();
  }

  public function applyInternalEffects($object, $value) {
    $key = $this->getStorageKey();
    return $object->setPlanProperty($key, $value);
  }

  public function getTitle() {
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    $behavior = $this->getBehavior();
    if ($behavior) {
      $behavior_name = $behavior->getName();

      $options = $behavior->getOptions();
      if (isset($options[$old_value])) {
        $old_value = $options[$old_value]->getName();
      }

      if (isset($options[$new_value])) {
        $new_value = $options[$new_value]->getName();
      }
    } else {
      $behavior_name = $this->getBehaviorKey();
    }

    return pht(
      '%s changed the %s behavior for this plan from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($behavior_name),
      $this->renderValue($old_value),
      $this->renderValue($new_value));
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $behaviors = HarbormasterBuildPlanBehavior::newPlanBehaviors();
    $behaviors = mpull($behaviors, null, 'getKey');

    foreach ($xactions as $xaction) {
      $key = $this->getBehaviorKeyForTransaction($xaction);

      if (!isset($behaviors[$key])) {
        $errors[] = $this->newInvalidError(
          pht(
            'No behavior with key "%s" exists. Valid keys are: %s.',
            $key,
            implode(', ', array_keys($behaviors))),
          $xaction);
        continue;
      }

      $behavior = $behaviors[$key];
      $options = $behavior->getOptions();

      $storage_key = HarbormasterBuildPlanBehavior::getStorageKeyForBehaviorKey(
        $key);
      $old = $object->getPlanProperty($storage_key);
      $new = $xaction->getNewValue();

      if ($old === $new) {
        continue;
      }

      if (!isset($options[$new])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Value "%s" is not a valid option for behavior "%s". Valid '.
            'options are: %s.',
            $new,
            $key,
            implode(', ', array_keys($options))),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'behavior';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'key' => $this->getBehaviorKeyForTransaction($xaction),
      'old' => $xaction->getOldValue(),
      'new' => $xaction->getNewValue(),
    );
  }

  private function getBehaviorKeyForTransaction(
    PhabricatorApplicationTransaction $xaction) {
    $metadata_key = HarbormasterBuildPlanBehavior::getTransactionMetadataKey();
    return $xaction->getMetadataValue($metadata_key);
  }

  private function getBehaviorKey() {
    $metadata_key = HarbormasterBuildPlanBehavior::getTransactionMetadataKey();
    return $this->getMetadataValue($metadata_key);
  }

  private function getBehavior() {
    $behavior_key = $this->getBehaviorKey();
    $behaviors = HarbormasterBuildPlanBehavior::newPlanBehaviors();
    return idx($behaviors, $behavior_key);
  }

  private function getStorageKey() {
    return HarbormasterBuildPlanBehavior::getStorageKeyForBehaviorKey(
      $this->getBehaviorKey());
  }

}
