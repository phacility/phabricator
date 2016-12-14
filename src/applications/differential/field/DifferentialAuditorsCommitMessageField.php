<?php

final class DifferentialAuditorsCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'phabricator:auditors';

  public function getFieldName() {
    return pht('Auditors');
  }

  public function parseFieldValue($value) {
    return $this->parseObjectList(
      $value,
      array(
        PhabricatorPeopleUserPHIDType::TYPECONST,
        PhabricatorProjectProjectPHIDType::TYPECONST,
      ));
  }

}
