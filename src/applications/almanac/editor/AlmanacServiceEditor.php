<?php

final class AlmanacServiceEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Service');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this service.', $author);
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

  protected function validateAllTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $errors = parent::validateAllTransactions($object, $xactions);

    if ($object->isClusterService()) {
      $can_manage = PhabricatorPolicyFilter::hasCapability(
        $this->getActor(),
        new PhabricatorAlmanacApplication(),
        AlmanacManageClusterServicesCapability::CAPABILITY);
      if (!$can_manage) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          null,
          pht('Restricted'),
          pht('You do not have permission to manage cluster services.'),
          null);
      }
    }

    return $errors;
  }

}
