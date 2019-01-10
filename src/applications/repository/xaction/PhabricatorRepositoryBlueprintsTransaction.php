<?php

final class PhabricatorRepositoryBlueprintsTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:automation-blueprints';

  public function generateOldValue($object) {
    return $object->getDetail('automation.blueprintPHIDs', array());
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('automation.blueprintPHIDs', $value);
  }

  public function applyExternalEffects($object, $value) {
    DrydockAuthorization::applyAuthorizationChanges(
      $this->getActor(),
      $object->getPHID(),
      $this->getOldValue(),
      $this->getNewValue());
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    if ($add && $rem) {
      return pht(
        '%s changed %s automation blueprint(s), '.
        'added %s: %s; removed %s: %s.',
        $this->renderAuthor(),
        new PhutilNumber(count($add) + count($rem)),
        new PhutilNumber(count($add)),
        $this->renderHandleList($add),
        new PhutilNumber(count($rem)),
        $this->renderHandleList($rem));
    } else if ($add) {
      return pht(
        '%s added %s automation blueprint(s): %s.',
        $this->renderAuthor(),
        new PhutilNumber(count($add)),
        $this->renderHandleList($add));
    } else {
      return pht(
        '%s removed %s automation blueprint(s): %s.',
        $this->renderAuthor(),
        new PhutilNumber(count($rem)),
        $this->renderHandleList($rem));
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $old = nonempty($xaction->getOldValue(), array());
      $new = nonempty($xaction->getNewValue(), array());

      $add = array_diff($new, $old);

      $invalid = PhabricatorObjectQuery::loadInvalidPHIDsForViewer(
        $this->getActor(),
        $add);
      if ($invalid) {
        $errors[] = $this->newInvalidError(
          pht(
            'Some of the selected automation blueprints are invalid '.
            'or restricted: %s.',
            implode(', ', $invalid)),
          $xaction);
      }
    }

    return $errors;
  }

}
