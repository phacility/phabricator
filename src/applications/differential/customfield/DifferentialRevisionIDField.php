<?php

final class DifferentialRevisionIDField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:revision-id';
  }

  public function getFieldKeyForConduit() {
    return 'revisionID';
  }

  public function getFieldName() {
    return pht('Differential Revision');
  }

  public function getFieldDescription() {
    return pht(
      'Ties commits to revisions and provides a permananent link between '.
      'them.');
  }

  public function canDisableField() {
    return false;
  }

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function shouldAllowEditInCommitMessage() {
    return false;
  }

  public function parseValueFromCommitMessage($value) {
    return DifferentialRevisionIDFieldSpecification::parseRevisionIDFromURI(
      $value);
  }

  public function renderCommitMessageValue(array $handles) {
    return PhabricatorEnv::getProductionURI('/D'.$this->getObject()->getID());
  }

}
