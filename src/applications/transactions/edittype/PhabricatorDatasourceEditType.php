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

}
