<?php

final class HarbormasterBuildPlanStatusTransaction
  extends HarbormasterBuildPlanTransactionType {

  const TRANSACTIONTYPE = 'harbormaster:status';

  public function generateOldValue($object) {
    return $object->getPlanStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPlanStatus($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new === HarbormasterBuildPlan::STATUS_DISABLED) {
      return pht(
        '%s disabled this build plan.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this build plan.',
        $this->renderAuthor());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $options = array(
      HarbormasterBuildPlan::STATUS_DISABLED,
      HarbormasterBuildPlan::STATUS_ACTIVE,
    );
    $options = array_fuse($options);

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (!isset($options[$new])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Status "%s" is not a valid build plan status. Valid '.
            'statuses are: %s.',
            $new,
            implode(', ', $options)));
        continue;
      }

    }

    return $errors;
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'status';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'old' => $xaction->getOldValue(),
      'new' => $xaction->getNewValue(),
    );
  }

}
