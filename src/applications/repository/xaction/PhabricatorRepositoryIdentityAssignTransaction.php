<?php

final class PhabricatorRepositoryIdentityAssignTransaction
  extends PhabricatorRepositoryIdentityTransactionType {

  const TRANSACTIONTYPE = 'repository:identity:assign';

  public function generateOldValue($object) {
    return nonempty($object->getManuallySetUserPHID(), null);
  }

  public function applyInternalEffects($object, $value) {
    $object->setManuallySetUserPHID($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!$old) {
      return pht(
        '%s assigned this identity to %s.',
        $this->renderAuthor(),
        $this->renderIdentityHandle($new));
    } else if (!$new) {
      return pht(
        '%s removed %s as the assignee of this identity.',
        $this->renderAuthor(),
        $this->renderIdentityHandle($old));
    } else {
      return pht(
        '%s changed the assigned user for this identity from %s to %s.',
        $this->renderAuthor(),
        $this->renderIdentityHandle($old),
        $this->renderIdentityHandle($new));
    }
  }

  private function renderIdentityHandle($handle) {
    $unassigned_token = DiffusionIdentityUnassignedDatasource::FUNCTION_TOKEN;
    if ($handle === $unassigned_token) {
      return phutil_tag('em', array(), pht('Explicitly Unassigned'));
    } else {
      return $this->renderHandle($handle);
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $unassigned_token = DiffusionIdentityUnassignedDatasource::FUNCTION_TOKEN;

    foreach ($xactions as $xaction) {
      $old = $xaction->getOldValue();
      $new = $xaction->getNewValue();
      if (!strlen($new)) {
        continue;
      }

      if ($new === $old) {
        continue;
      }

      if ($new === $unassigned_token) {
        continue;
      }

      $assignee_list = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($new))
        ->execute();

      if (!$assignee_list) {
        $errors[] = $this->newInvalidError(
          pht('User "%s" is not a valid user.',
          $new));
      }
    }
    return $errors;
  }

}
