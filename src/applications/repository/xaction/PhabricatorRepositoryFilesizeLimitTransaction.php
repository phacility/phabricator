<?php

final class PhabricatorRepositoryFilesizeLimitTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'limit.filesize';

  public function generateOldValue($object) {
    return $object->getFilesizeLimit();
  }

  public function generateNewValue($object, $value) {
    if (!strlen($value)) {
      return null;
    }

    $value = phutil_parse_bytes($value);
    if (!$value) {
      return null;
    }

    return $value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setFilesizeLimit($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old && $new) {
      return pht(
        '%s changed the filesize limit for this repository from %s bytes to '.
        '%s bytes.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if ($new) {
      return pht(
        '%s set the filesize limit for this repository to %s bytes.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s removed the filesize limit (%s bytes) for this repository.',
        $this->renderAuthor(),
        $this->renderOldValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (!strlen($new)) {
        continue;
      }

      try {
        $value = phutil_parse_bytes($new);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          pht(
            'Unable to parse filesize limit: %s',
            $ex->getMessage()),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
