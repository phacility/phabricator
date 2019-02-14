<?php

final class PhabricatorAuthContactNumberEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Contact Numbers');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this contact number.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();
    $errors[] = new PhabricatorApplicationTransactionValidationError(
      PhabricatorAuthContactNumberNumberTransaction::TRANSACTIONTYPE,
      pht('Duplicate'),
      pht('This contact number is already in use.'),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }


}
