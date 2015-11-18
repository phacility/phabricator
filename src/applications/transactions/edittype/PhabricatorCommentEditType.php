<?php

final class PhabricatorCommentEditType extends PhabricatorEditType {

  public function getValueType() {
    return id(new AphrontStringHTTPParameterType())->getTypeName();
  }

  public function generateTransaction(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $comment = $template->getApplicationTransactionCommentObject()
      ->setContent(idx($spec, 'value'));

    $template
      ->setTransactionType($this->getTransactionType())
      ->attachComment($comment);

    foreach ($this->getMetadata() as $key => $value) {
      $template->setMetadataValue($key, $value);
    }

    return $template;
  }

  public function getValueDescription() {
    return pht('Comment to add, formated as remarkup.');
  }

}
