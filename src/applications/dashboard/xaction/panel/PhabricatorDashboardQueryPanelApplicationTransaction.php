<?php

final class PhabricatorDashboardQueryPanelApplicationTransaction
  extends PhabricatorDashboardPanelPropertyTransaction {

  const TRANSACTIONTYPE = 'query.application';

  protected function getPropertyKey() {
    return 'class';
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $engines = PhabricatorApplicationSearchEngine::getAllEngines();

    $old_value = $object->getProperty($this->getPropertyKey());
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if ($new_value === $old_value) {
        continue;
      }

      if (!isset($engines[$new_value])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Application search engine class "%s" is unknown. Query panels '.
            'must use a known search engine class.',
            $new_value),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
