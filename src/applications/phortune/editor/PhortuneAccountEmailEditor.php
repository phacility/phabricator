<?php

final class PhortuneAccountEmailEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phortune Account Emails');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this account email.', $author);
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();

    $errors[] = new PhabricatorApplicationTransactionValidationError(
      PhortuneAccountEmailAddressTransaction::TRANSACTIONTYPE,
      pht('Duplicate'),
      pht(
        'The email address "%s" is already attached to this account.',
        $object->getAddress()),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }

}
