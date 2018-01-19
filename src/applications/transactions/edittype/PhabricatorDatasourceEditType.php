<?php

final class PhabricatorDatasourceEditType
  extends PhabricatorPHIDListEditType {

  public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $value = idx($spec, 'value');

    $xaction = $this->newTransaction($template)
      ->setNewValue($value);

    return array($xaction);
  }

  public function getValueDescription() {
    return '?';
  }

  public function newRawBulkTransaction(array $xaction) {
    $value = idx($xaction, 'value');

    if ($this->getIsSingleValue()) {
      if ($value) {
        $value = head($value);
      } else {
        $value = null;
      }

      $xaction['value'] = $value;
    }

    return $xaction;
  }

}
