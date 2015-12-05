<?php

final class PhabricatorCommentEditType extends PhabricatorEditType {

  public function getValueType() {
    return id(new AphrontStringHTTPParameterType())->getTypeName();
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

  public function getValueDescription() {
    return pht('Comment to add, formated as remarkup.');
  }

}
