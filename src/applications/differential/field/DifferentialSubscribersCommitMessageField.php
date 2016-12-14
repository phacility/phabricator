<?php

final class DifferentialSubscribersCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'ccPHIDs';

  public function getFieldName() {
    return pht('Subscribers');
  }

  public function getFieldAliases() {
    return array(
      'CC',
      'CCs',
      'Subscriber',
    );
  }

  public function parseFieldValue($value) {
    return $this->parseObjectList(
      $value,
      array(
        PhabricatorPeopleUserPHIDType::TYPECONST,
        PhabricatorProjectProjectPHIDType::TYPECONST,
        PhabricatorOwnersPackagePHIDType::TYPECONST,
      ));
  }

}
