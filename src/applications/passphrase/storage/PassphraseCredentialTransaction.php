<?php

final class PassphraseCredentialTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'passphrase:name';
  const TYPE_DESCRIPTION = 'passphrase:description';
  const TYPE_USERNAME = 'passphrase:username';
  const TYPE_SECRET_ID = 'passphrase:secretID';
  const TYPE_DESTROY = 'passphrase:destroy';
  const TYPE_LOOKEDATSECRET = 'passphrase:lookedAtSecret';
  const TYPE_LOCK = 'passphrase:lock';
  const TYPE_CONDUIT = 'passphrase:conduit';

  public function getApplicationName() {
    return 'passphrase';
  }

  public function getApplicationTransactionType() {
    return PassphraseCredentialPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($old === null);
      case self::TYPE_LOCK:
        return ($old === null);
      case self::TYPE_USERNAME:
        return !strlen($old);
      case self::TYPE_LOOKEDATSECRET:
        return false;
      case self::TYPE_DESTROY:
        // Don't show "undestroy" transactions because they're a bit confusing
        // and redundant with restoring a secret.
        if (!$new) {
          return true;
        }
    }
    return parent::shouldHide();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_phid = $this->getAuthorPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this credential.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this credential from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for this credential.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_USERNAME:
        if (strlen($old)) {
          return pht(
            '%s changed the username for this credential from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        } else {
          return pht(
            '%s set the username for this credential to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        break;
      case self::TYPE_SECRET_ID:
        if ($old === null) {
          return pht(
            '%s attached a new secret to this credential.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s updated the secret for this credential.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_DESTROY:
        return pht(
          '%s destroyed the secret for this credential.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_LOOKEDATSECRET:
        return pht(
          '%s examined the secret plaintext for this credential.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_LOCK:
        return pht(
          '%s locked this credential.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_CONDUIT:
        if ($old) {
          return pht(
            '%s disallowed Conduit API access to this credential.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s allowed Conduit API access to this credential.',
            $this->renderHandleLink($author_phid));
        }
        break;
    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    return $this->renderTextCorpusChangeDetails(
      $viewer,
      json_encode($this->getOldValue()),
      json_encode($this->getNewValue()));
  }

}
