<?php

final class PhabricatorRepositorySVNSubpathTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:svn-subpath';

  public function generateOldValue($object) {
    return $object->getDetail('svn-subpath');
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('svn-subpath', $value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!strlen($new)) {
      return pht(
        '%s removed %s as the "Import Only" path.',
        $this->renderAuthor(),
        $this->renderOldValue());
    } else if (!strlen($old)) {
      return pht(
        '%s set the repository "Import Only" path to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s changed the "Import Only" path from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

}
