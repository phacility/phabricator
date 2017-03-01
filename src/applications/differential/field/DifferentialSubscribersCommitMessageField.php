<?php

final class DifferentialSubscribersCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'ccPHIDs';

  public function getFieldName() {
    return pht('Subscribers');
  }

  public function getFieldOrder() {
    return 6000;
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

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    if (!$revision->getPHID()) {
      return array();
    }

    return PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $revision->getPHID());
  }

  public function readFieldValueFromConduit($value) {
    return $this->readStringListFieldValueFromConduit($value);
  }

  public function renderFieldValue($value) {
    return $this->renderHandleList($value);
  }

  public function getFieldTransactions($value) {
    return array(
      array(
        'type' => PhabricatorSubscriptionsEditEngineExtension::EDITKEY_SET,
        'value' => $value,
      ),
    );
  }

}
