<?php

final class FundInitiativeTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'fund:name';
  const TYPE_DESCRIPTION = 'fund:description';
  const TYPE_RISKS = 'fund:risks';
  const TYPE_STATUS = 'fund:status';
  const TYPE_BACKER = 'fund:backer';
  const TYPE_REFUND = 'fund:refund';
  const TYPE_MERCHANT = 'fund:merchant';

  const MAILTAG_BACKER = 'fund.backer';
  const MAILTAG_STATUS = 'fund.status';
  const MAILTAG_OTHER  = 'fund.other';

  const PROPERTY_AMOUNT = 'fund.amount';
  const PROPERTY_BACKER = 'fund.backer';

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
      case self::TYPE_MERCHANT:
        if ($old) {
          $phids[] = $old;
        }
        if ($new) {
          $phids[] = $new;
        }
        break;
      case self::TYPE_REFUND:
        $phids[] = $this->getMetadataValue(self::PROPERTY_BACKER);
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
      case self::TYPE_NAME:
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
      case self::TYPE_RISKS:
        return pht(
          '%s edited the risks for this initiative.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s edited the description of this initiative.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_STATUS:
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
      case self::TYPE_BACKER:
        $amount = $this->getMetadataValue(self::PROPERTY_AMOUNT);
        $amount = PhortuneCurrency::newFromString($amount);
        return pht(
          '%s backed this initiative with %s.',
          $this->renderHandleLink($author_phid),
          $amount->formatForDisplay());
      case self::TYPE_REFUND:
        $amount = $this->getMetadataValue(self::PROPERTY_AMOUNT);
        $amount = PhortuneCurrency::newFromString($amount);

        $backer_phid = $this->getMetadataValue(self::PROPERTY_BACKER);

        return pht(
          '%s refunded %s to %s.',
          $this->renderHandleLink($author_phid),
          $amount->formatForDisplay(),
          $this->renderHandleLink($backer_phid));
      case self::TYPE_MERCHANT:
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

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_NAME:
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
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_STATUS:
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
      case self::TYPE_BACKER:
        $amount = $this->getMetadataValue(self::PROPERTY_AMOUNT);
        $amount = PhortuneCurrency::newFromString($amount);
        return pht(
          '%s backed %s with %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $amount->formatForDisplay());
      case self::TYPE_REFUND:
        $amount = $this->getMetadataValue(self::PROPERTY_AMOUNT);
        $amount = PhortuneCurrency::newFromString($amount);

        $backer_phid = $this->getMetadataValue(self::PROPERTY_BACKER);

        return pht(
          '%s refunded %s to %s for %s.',
          $this->renderHandleLink($author_phid),
          $amount->formatForDisplay(),
          $this->renderHandleLink($backer_phid),
          $this->renderHandleLink($object_phid));
    }

    return parent::getTitleForFeed();
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case self::TYPE_STATUS:
        $tags[] = self::MAILTAG_STATUS;
        break;
      case self::TYPE_BACKER:
      case self::TYPE_REFUND:
        $tags[] = self::MAILTAG_BACKER;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }

    return $tags;
  }


  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
      case self::TYPE_RISKS:
        return ($old === null);
    }
    return parent::shouldHide();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
      case self::TYPE_RISKS:
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
