<?php

final class PhabricatorRepositorySymbolSourcesTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:symbol-source';

  public function generateOldValue($object) {
    return $object->getSymbolSources();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('symbol-sources', $value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old) {
      $display_old = $this->renderHandleList($old);
    } else {
      $display_old = $this->renderValue(pht('None'));
    }

    if ($new) {
      $display_new = $this->renderHandleList($new);
    } else {
      $display_new = $this->renderValue(pht('None'));
    }

    return pht(
      '%s changed symbol sources from %s to %s.',
      $this->renderAuthor(),
      $display_old,
      $display_new);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $old = $object->getSymbolSources();
      $new = $xaction->getNewValue();

      // If the viewer is adding new repositories, make sure they are
      // valid and visible.
      $add = array_diff($new, $old);
      if (!$add) {
        continue;
      }

      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getActor())
        ->withPHIDs($add)
        ->execute();
      $repositories = mpull($repositories, null, 'getPHID');

      foreach ($add as $phid) {
        if (isset($repositories[$phid])) {
          continue;
        }

        $errors[] = $this->newInvalidError(
          pht(
            'Repository ("%s") does not exist, or you do not have '.
            'permission to see it.',
            $phid),
          $xaction);
        break;
      }
    }

    return $errors;
  }


}
