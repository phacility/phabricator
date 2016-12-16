<?php

final class DifferentialReviewedByCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'reviewedByPHIDs';

  public function getFieldName() {
    return pht('Reviewed By');
  }

  public function getFieldOrder() {
    return 5000;
  }

  public function parseFieldValue($value) {
    return $this->parseObjectList(
      $value,
      array(
        PhabricatorPeopleUserPHIDType::TYPECONST,
        PhabricatorProjectProjectPHIDType::TYPECONST,
      ),
      $allow_partial = true);
  }

  public function isFieldEditable() {
    return false;
  }

  public function isTemplateField() {
    return false;
  }

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    if (!$revision->getPHID()) {
      return array();
    }

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

  public function readFieldValueFromConduit($value) {
    return $this->readStringListFieldValueFromConduit($value);
  }

  public function renderFieldValue($value) {
    return $this->renderHandleList($value);
  }

}
