<?php

final class AlmanacNamespaceEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Namespace');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this namespace.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function supportsSearch() {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();

    $errors[] = new PhabricatorApplicationTransactionValidationError(
      null,
      pht('Invalid'),
      pht(
        'Another namespace with this name already exists. Each namespace '.
        'must have a unique name.'),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }

}
