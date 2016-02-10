<?php

final class PhabricatorSimpleEditType extends PhabricatorEditType {

  public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $edit = $this->newTransaction($template)
      ->setNewValue(idx($spec, 'value'));

    return array($edit);
  }

}
