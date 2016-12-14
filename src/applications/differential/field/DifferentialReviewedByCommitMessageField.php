<?php

final class DifferentialReviewedByCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'reviewedByPHIDs';

  public function getFieldName() {
    return pht('Reviewed By');
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

}
