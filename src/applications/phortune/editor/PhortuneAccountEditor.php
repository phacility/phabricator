<?php

final class PhortuneAccountEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phortune Accounts');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this payment account.', $author);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhabricatorTransactions::TYPE_EDGE;
    return $types;
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorTransactions::TYPE_EDGE:
        foreach ($xactions as $xaction) {
          switch ($xaction->getMetadataValue('edge:type')) {
            case PhortuneAccountHasMemberEdgeType::EDGECONST:
              $actor_phid = $this->requireActor()->getPHID();
              $new = $xaction->getNewValue();
              $old = $object->getMemberPHIDs();

              // Check if user is trying to not set themselves on creation
              if (!$old) {
                $set = idx($new, '+', array());
                $actor_set = false;
                foreach ($set as $phid) {
                  if ($actor_phid == $phid) {
                    $actor_set = true;
                  }
                }
                if (!$actor_set) {
                  $error = new PhabricatorApplicationTransactionValidationError(
                    $type,
                    pht('Invalid'),
                    pht('You can not remove yourself as an account manager.'),
                    $xaction);
                  $errors[] = $error;

                }
              }

              // Check if user is trying to remove themselves on edit
              $set = idx($new, '-', array());
              foreach ($set as $phid) {
                if ($actor_phid == $phid) {
                  $error = new PhabricatorApplicationTransactionValidationError(
                    $type,
                    pht('Invalid'),
                    pht('You can not remove yourself as an account manager.'),
                    $xaction);
                  $errors[] = $error;

                }
              }
            break;
          }
        }
        break;
    }
    return $errors;
  }

}
