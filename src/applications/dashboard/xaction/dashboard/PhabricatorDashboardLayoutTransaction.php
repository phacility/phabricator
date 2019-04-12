<?php

final class PhabricatorDashboardLayoutTransaction
  extends PhabricatorDashboardTransactionType {

  const TRANSACTIONTYPE = 'dashboard:layoutmode';

  public function generateOldValue($object) {
    return $object->getRawLayoutMode();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRawLayoutMode($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    return pht(
      '%s changed the layout mode for this dashboard from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $mode_map = PhabricatorDashboardLayoutMode::getLayoutModeMap();

    $old_value = $object->getRawLayoutMode();
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if ($new_value === $old_value) {
        continue;
      }

      if (!isset($mode_map[$new_value])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Layout mode "%s" is not valid. Supported layout modes '.
            'are: %s.',
            $new_value,
            implode(', ', array_keys($mode_map))),
          $xaction);
        continue;
      }
    }

    return $errors;
  }


}
