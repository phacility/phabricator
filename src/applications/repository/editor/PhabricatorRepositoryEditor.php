<?php

final class PhabricatorRepositoryEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorRepositoryTransaction::TYPE_NAME;
    $types[] = PhabricatorRepositoryTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorRepositoryTransaction::TYPE_ENCODING;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
        return $object->getDetail('description');
      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
        return $object->getDetail('encoding');
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_NAME:
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
        $object->setDetail('description', $xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
        // Make sure the encoding is valid by converting to UTF-8. This tests
        // that the user has mbstring installed, and also that they didn't type
        // a garbage encoding name. Note that we're converting from UTF-8 to
        // the target encoding, because mbstring is fine with converting from
        // a nonsense encoding.
        $encoding = $xaction->getNewValue();
        if (strlen($encoding)) {
          try {
            phutil_utf8_convert('.', $encoding, 'UTF-8');
          } catch (Exception $ex) {
            throw new PhutilProxyException(
              pht(
                "Error setting repository encoding '%s': %s'",
                $encoding,
                $ex->getMessage()),
              $ex);
          }
        }
        $object->setDetail('encoding', $encoding);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $type = $xaction->getTransactionType();
    switch ($type) {

    }

    return parent::transactionHasEffect($object, $xaction);
  }

}
