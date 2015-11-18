<?php

final class PhabricatorCommentEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new PhabricatorRemarkupControl();
  }

  protected function newEditType() {
    return new PhabricatorCommentEditType();
  }

  public function generateTransaction(
    PhabricatorApplicationTransaction $xaction) {

    $spec = array(
      'value' => $this->getValueForTransaction(),
    );

    return head($this->getEditTransactionTypes())
      ->generateTransaction($xaction, $spec);
  }

}
