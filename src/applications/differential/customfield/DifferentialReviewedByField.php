<?php

final class DifferentialReviewedByField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:reviewed-by';
  }

  public function getFieldKeyForConduit() {
    return 'reviewedByPHIDs';
  }

  public function getFieldName() {
    return pht('Reviewed By');
  }

  public function getFieldDescription() {
    return pht('Records accepting reviewers in the durable message.');
  }

  public function shouldAppearInApplicationTransactions() {
    return false;
  }

  public function shouldAppearInEditView() {
    return false;
  }

  public function canDisableField() {
    return true;
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {

    $phids = array();
    foreach ($revision->getReviewerStatus() as $reviewer) {
      switch ($reviewer->getStatus()) {
        case DifferentialReviewerStatus::STATUS_ACCEPTED:
        case DifferentialReviewerStatus::STATUS_ACCEPTED_OLDER:
          $phids[] = $reviewer->getReviewerPHID();
          break;
      }
    }

    return $phids;
  }

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function parseValueFromCommitMessage($value) {
    return $this->parseObjectList(
      $value,
      array(
        PhabricatorPeopleUserPHIDType::TYPECONST,
        PhabricatorProjectProjectPHIDType::TYPECONST,
      ),
      $allow_partial = true);
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->getValue();
  }

  public function renderCommitMessageValue(array $handles) {
    return $this->renderObjectList($handles);
  }

}
