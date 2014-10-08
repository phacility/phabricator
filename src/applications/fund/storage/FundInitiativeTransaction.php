<?php

final class FundInitiativeTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'fund:name';
  const TYPE_DESCRIPTION = 'fund:description';
  const TYPE_RISKS = 'fund:risks';
  const TYPE_STATUS = 'fund:status';
  const TYPE_BACKER = 'fund:backer';
  const TYPE_MERCHANT = 'fund:merchant';

  public function getApplicationName() {
    return 'fund';
  }

  public function getApplicationTransactionType() {
    return FundInitiativePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case FundInitiativeTransaction::TYPE_MERCHANT:
        if ($old) {
          $phids[] = $old;
        }
        if ($new) {
          $phids[] = $new;
        }
        break;
    }

    return $phids;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case FundInitiativeTransaction::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this initiative.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this initiative from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case FundInitiativeTransaction::TYPE_RISKS:
        return pht(
          '%s edited the risks for this initiative.',
          $this->renderHandleLink($author_phid));
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
        return pht(
          '%s edited the description of this initiative.',
          $this->renderHandleLink($author_phid));
      case FundInitiativeTransaction::TYPE_STATUS:
        switch ($new) {
          case FundInitiative::STATUS_OPEN:
            return pht(
              '%s reopened this initiative.',
              $this->renderHandleLink($author_phid));
          case FundInitiative::STATUS_CLOSED:
            return pht(
              '%s closed this initiative.',
              $this->renderHandleLink($author_phid));
        }
        break;
      case FundInitiativeTransaction::TYPE_BACKER:
        return pht(
          '%s backed this initiative.',
          $this->renderHandleLink($author_phid));
      case FundInitiativeTransaction::TYPE_MERCHANT:
        if ($old === null) {
          return pht(
            '%s set this initiative to pay to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s changed the merchant receiving funds from this '.
            'initiative from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
    }

    return parent::getTitle();
  }

  public function getTitleForFeed(PhabricatorFeedStory $story) {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case FundInitiativeTransaction::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));

        } else {
          return pht(
            '%s renamed %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case FundInitiativeTransaction::TYPE_STATUS:
        switch ($new) {
          case FundInitiative::STATUS_OPEN:
            return pht(
              '%s reopened %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case FundInitiative::STATUS_CLOSED:
            return pht(
              '%s closed %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
        }
        break;
      case FundInitiativeTransaction::TYPE_BACKER:
        return pht(
          '%s backed %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
    }

    return parent::getTitleForFeed($story);
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
      case FundInitiativeTransaction::TYPE_RISKS:
        return ($old === null);
    }
    return parent::shouldHide();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
      case FundInitiativeTransaction::TYPE_RISKS:
        return ($this->getOldValue() !== null);
    }

    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $this->getOldValue(),
      $this->getNewValue());
  }
}
