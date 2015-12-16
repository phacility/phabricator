<?php

final class PhabricatorCommentEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new PhabricatorRemarkupControl();
  }

  protected function newEditType() {
    return new PhabricatorCommentEditType();
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

  public function shouldGenerateTransactionsFromSubmit() {
    return false;
  }

  public function shouldGenerateTransactionsFromComment() {
    return true;
  }

}
