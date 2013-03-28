<?php

final class PhortuneAccountTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME   = 'phortune:name';

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_ACNT;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getApplicationObjectTypeName() {
    return pht('account');
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this account.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this account from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($this->getMetadataValue('edge:type')) {
          case PhabricatorEdgeConfig::TYPE_ACCOUNT_HAS_MEMBER:
            $add = array_diff(array_keys($new), array_keys($old));
            $rem = array_diff(array_keys($old), array_keys($new));
            if ($add && $rem) {
              return pht(
                '%s changed account members, added %s; removed %s.',
                $this->renderHandleLink($author_phid),
                $this->renderHandleList($add),
                $this->renderHandleList($rem));
            } else if ($add) {
              return pht(
                '%s added account members: %s',
                $this->renderHandleLink($author_phid),
                $this->renderHandleList($add));
            } else {
              return pht(
                '%s removed account members: %s',
                $this->renderHandleLink($author_phid),
                $this->renderHandleList($add));
            }
            break;
        }
        break;
    }

    return parent::getTitle();
  }

}
