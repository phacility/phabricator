<?php

abstract class AlmanacTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_PROPERTY_UPDATE = 'almanac:property:update';
  const TYPE_PROPERTY_REMOVE = 'almanac:property:remove';

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_PROPERTY_UPDATE:
        $property_key = $this->getMetadataValue('almanac.property');
        return pht(
          '%s updated the property "%s".',
          $this->renderHandleLink($author_phid),
          $property_key);
      case self::TYPE_PROPERTY_REMOVE:
        $property_key = $this->getMetadataValue('almanac.property');
        return pht(
          '%s deleted the property "%s".',
          $this->renderHandleLink($author_phid),
          $property_key);
    }

    return parent::getTitle();
  }

}
