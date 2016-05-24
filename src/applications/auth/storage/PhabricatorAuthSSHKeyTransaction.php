<?php

final class PhabricatorAuthSSHKeyTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'sshkey.name';
  const TYPE_KEY = 'sshkey.key';
  const TYPE_DEACTIVATE = 'sshkey.deactivate';

  public function getApplicationName() {
    return 'auth';
  }

  public function getApplicationTransactionType() {
    return PhabricatorAuthSSHKeyPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s created this key.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_NAME:
        return pht(
          '%s renamed this key from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_KEY:
        return pht(
          '%s updated the public key material for this SSH key.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_DEACTIVATE:
        if ($new) {
          return pht(
            '%s deactivated this key.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s activated this key.',
            $this->renderHandleLink($author_phid));
        }

    }

    return parent::getTitle();
  }

}
