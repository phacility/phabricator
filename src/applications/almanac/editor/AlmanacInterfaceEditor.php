<?php

final class AlmanacInterfaceEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Interface');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this interface.', $author);
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
      null,
      pht('Invalid'),
      pht(
        'Interfaces must have a unique combination of network, device, '.
        'address, and port.'),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }

}
