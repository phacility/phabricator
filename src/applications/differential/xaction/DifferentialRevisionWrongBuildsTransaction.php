<?php

final class DifferentialRevisionWrongBuildsTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.builds.wrong';

  public function generateOldValue($object) {
    return null;
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setProperty(DifferentialRevision::PROPERTY_WRONG_BUILDS, true);
  }

  public function getIcon() {
    return 'fa-exclamation';
  }

  public function getColor() {
    return 'pink';
  }

  public function getActionStrength() {
    return 400;
  }

  public function getTitle() {
    return pht(
      'This revision was landed with ongoing or failed builds.');
  }

  public function shouldHideForFeed() {
    return true;
  }

}
