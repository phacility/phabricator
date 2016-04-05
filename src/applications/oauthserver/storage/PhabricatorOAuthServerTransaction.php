<?php

final class PhabricatorOAuthServerTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'oauthserver.name';
  const TYPE_REDIRECT_URI = 'oauthserver.redirect-uri';
  const TYPE_DISABLED = 'oauthserver.disabled';

  public function getApplicationName() {
    return 'oauth_server';
  }

  public function getTableName() {
    return 'oauth_server_transaction';
  }

  public function getApplicationTransactionType() {
    return PhabricatorOAuthServerClientPHIDType::TYPECONST;
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
          '%s created this OAuth application.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_NAME:
        return pht(
          '%s renamed this application from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_REDIRECT_URI:
        return pht(
          '%s changed the application redirect URI from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_DISABLED:
        if ($new) {
          return pht(
            '%s disabled this application.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s enabled this application.',
            $this->renderHandleLink($author_phid));
        }
    }

    return parent::getTitle();
  }

}
