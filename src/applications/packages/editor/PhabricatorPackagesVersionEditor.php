<?php

final class PhabricatorPackagesVersionEditor
  extends PhabricatorPackagesEditor {

  public function getEditorObjectsDescription() {
    return pht('Package Versions');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this version.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array();
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();
    $errors[] = new PhabricatorApplicationTransactionValidationError(
      PhabricatorPackagesVersionNameTransaction::TRANSACTIONTYPE,
      pht('Duplicate'),
      pht(
        'The version "%s" already exists for this package. Each version '.
        'must have a unique name.',
        $object->getName()),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }

}
