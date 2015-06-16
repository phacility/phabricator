<?php

/**
 * This field doesn't do anything, it just parses the "Conflicts:" field which
 * `git` can insert after a merge, so we don't squish the field value into
 * some other field.
 */
final class DifferentialConflictsField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:conflicts';
  }

  public function getFieldKeyForConduit() {
    return 'conflicts';
  }

  public function getFieldName() {
    return pht('Conflicts');
  }

  public function getFieldDescription() {
    return pht(
      'Parses the "%s" field which Git can inject into commit messages.',
      'Conflicts');
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

  public function renderCommitMessageValue(array $handles) {
    return null;
  }

}
