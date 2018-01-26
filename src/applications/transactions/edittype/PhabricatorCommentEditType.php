<?php

final class PhabricatorCommentEditType extends PhabricatorEditType {

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

  protected function newBulkParameterType() {
    return new BulkRemarkupParameterType();
  }

  public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $comment = $template->getApplicationTransactionCommentObject()
      ->setContent(idx($spec, 'value'));

    $xaction = $this->newTransaction($template)
      ->attachComment($comment);

    return array($xaction);
  }

}
