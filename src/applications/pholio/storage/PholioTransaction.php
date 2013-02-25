<?php

/**
 * @group pholio
 */
final class PholioTransaction extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'pholio';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_MOCK;
  }

  public function getApplicationTransactionCommentObject() {
    return new PholioTransactionComment();
  }

  public function getApplicationObjectTypeName() {
    return pht('mock');
  }

  public function shouldHide() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
      case PholioTransactionType::TYPE_DESCRIPTION:
        return ($old === null);
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
        return pht(
          '%s renamed this mock from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
        break;
      case PholioTransactionType::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description of this mock. '.
          'The old description was: %s',
          $this->renderHandleLink($author_phid),
          $old);
        break;
      case PholioTransactionType::TYPE_INLINE:
        return pht(
          '%s added an inline comment.',
          $this->renderHandleLink($author_phid));
    }

    return parent::getTitle();
  }


}
