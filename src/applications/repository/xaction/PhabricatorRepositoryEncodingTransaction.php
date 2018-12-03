<?php

final class PhabricatorRepositoryEncodingTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:encoding';

  public function generateOldValue($object) {
    return $object->getDetail('encoding');
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('encoding', $value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && !strlen($new)) {
      return pht(
        '%s removed the %s encoding configured for this repository.',
        $this->renderAuthor(),
        $this->renderOldValue());
    } else if (strlen($new) && !strlen($old)) {
      return pht(
        '%s set the encoding for this repository to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s changed the repository encoding from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      // Make sure the encoding is valid by converting to UTF-8. This tests
      // that the user has mbstring installed, and also that they didn't
      // type a garbage encoding name. Note that we're converting from
      // UTF-8 to the target encoding, because mbstring is fine with
      // converting from a nonsense encoding.
      $encoding = $xaction->getNewValue();
      if (!strlen($encoding)) {
        continue;
      }

      try {
        phutil_utf8_convert('.', $encoding, 'UTF-8');
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          pht(
            'Repository encoding "%s" is not valid: %s',
            $encoding,
            $ex->getMessage()),
          $xaction);
      }
    }

    return $errors;
  }

}
