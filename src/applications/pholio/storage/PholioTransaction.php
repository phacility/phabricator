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

  public function getApplicationTransactionViewObject() {
    return new PholioTransactionView();
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

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_INLINE:
        return 'comment';
    }
    return parent::getIcon();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case PholioTransactionType::TYPE_NAME:
        return pht(
          '%s renamed this mock from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
        break;
      case PholioTransactionType::TYPE_DESCRIPTION:
        return pht(
          "%s updated the mock's description.",
          $this->renderHandleLink($author_phid));
        break;
      case PholioTransactionType::TYPE_INLINE:
        $count = 1;
        foreach ($this->getTransactionGroup() as $xaction) {
          if ($xaction->getTransactionType() == $type) {
            $count++;
          }
        }

        return pht(
          '%s added %d inline comment(s).',
          $this->renderHandleLink($author_phid),
          $count);
    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_DESCRIPTION:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $view = id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setUser($viewer)
      ->setOldText($old)
      ->setNewText($new);

    return $view->render();
  }


}
