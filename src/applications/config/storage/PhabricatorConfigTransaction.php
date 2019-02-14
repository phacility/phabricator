<?php

final class PhabricatorConfigTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_EDIT = 'config:edit';

  public function getApplicationName() {
    return 'config';
  }

  public function getApplicationTransactionType() {
    return PhabricatorConfigConfigPHIDType::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_EDIT:

        // TODO: After T2213 show the actual values too; for now, we don't
        // have the tools to do it without making a bit of a mess of it.

        $old_del = idx($old, 'deleted');
        $new_del = idx($new, 'deleted');
        if ($old_del && !$new_del) {
          return pht(
            '%s created this configuration entry.',
            $this->renderHandleLink($author_phid));
        } else if (!$old_del && $new_del) {
          return pht(
            '%s deleted this configuration entry.',
            $this->renderHandleLink($author_phid));
        } else if ($old_del && $new_del) {
          // This is a bug.
          return pht(
            '%s deleted this configuration entry (again?).',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s edited this configuration entry.',
            $this->renderHandleLink($author_phid));
        }
        break;
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_EDIT:
        $old_del = idx($old, 'deleted');
        $new_del = idx($new, 'deleted');
        if ($old_del && !$new_del) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->getObject()->getConfigKey());
        } else if (!$old_del && $new_del) {
          return pht(
            '%s deleted %s.',
            $this->renderHandleLink($author_phid),
            $this->getObject()->getConfigKey());
        } else if ($old_del && $new_del) {
          // This is a bug.
          return pht(
            '%s deleted %s (again?).',
            $this->renderHandleLink($author_phid),
            $this->getObject()->getConfigKey());
        } else {
          return pht(
            '%s edited %s.',
            $this->renderHandleLink($author_phid),
            $this->getObject()->getConfigKey());
        }
        break;
    }

    return parent::getTitle();
  }


  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_EDIT:
        return 'fa-pencil';
    }

    return parent::getIcon();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_EDIT:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old['deleted']) {
      $old_text = '';
    } else {
      $old_text = PhabricatorConfigJSON::prettyPrintJSON($old['value']);
    }

    if ($new['deleted']) {
      $new_text = '';
    } else {
      $new_text = PhabricatorConfigJSON::prettyPrintJSON($new['value']);
    }

    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $old_text,
      $new_text);
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_EDIT:
        $old_del = idx($old, 'deleted');
        $new_del = idx($new, 'deleted');

        if ($old_del && !$new_del) {
          return PhabricatorTransactions::COLOR_GREEN;
        } else if (!$old_del && $new_del) {
          return PhabricatorTransactions::COLOR_RED;
        } else {
          return PhabricatorTransactions::COLOR_BLUE;
        }
        break;
    }
  }

}
