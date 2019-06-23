<?php

final class PhabricatorDashboardQueryPanelLimitTransaction
  extends PhabricatorDashboardPanelPropertyTransaction {

  const TRANSACTIONTYPE = 'search.limit';

  protected function getPropertyKey() {
    return 'limit';
  }

  public function generateNewValue($object, $value) {
    if (!$value) {
      return null;
    }

    return $value;
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $old_value = $object->getProperty($this->getPropertyKey());
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if ($new_value === $old_value) {
        continue;
      }

      if ($new_value < 0) {
        $errors[] = $this->newInvalidError(
          pht(
            'Query result limit must be empty, or at least 1.'),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
