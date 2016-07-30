<?php

final class PhabricatorPackagesPackageEditor
  extends PhabricatorPackagesEditor {

  public function getEditorObjectsDescription() {
    return pht('Package Packages');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this package.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
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
      PhabricatorPackagesPackageKeyTransaction::TRANSACTIONTYPE,
      pht('Duplicate'),
      pht(
        'The package key "%s" is already in use by another package provided '.
        'by this publisher.',
        $object->getPackageKey()),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }

}
