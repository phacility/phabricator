<?php

final class DifferentialReviewersCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'reviewerPHIDs';

  public function getFieldName() {
    return pht('Reviewers');
  }

  public function getFieldOrder() {
    return 4000;
  }

  public function getFieldAliases() {
    return array(
      'Reviewer',
    );
  }

  public function parseFieldValue($value) {
    $results = $this->parseObjectList(
      $value,
      array(
        PhabricatorPeopleUserPHIDType::TYPECONST,
        PhabricatorProjectProjectPHIDType::TYPECONST,
        PhabricatorOwnersPackagePHIDType::TYPECONST,
      ),
      false,
      array('!'));

    return $this->flattenReviewers($results);
  }

  public function readFieldValueFromConduit($value) {
    return $this->readStringListFieldValueFromConduit($value);
  }

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    if (!$revision->getPHID()) {
      return array();
    }

    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;

    $results = array();
    foreach ($revision->getReviewerStatus() as $reviewer) {
      if ($reviewer->getStatus() == $status_blocking) {
        $suffixes = array('!' => '!');
      } else {
        $suffixes = array();
      }

      $results[] = array(
        'phid' => $reviewer->getReviewerPHID(),
        'suffixes' => $suffixes,
      );
    }

    return $this->flattenReviewers($results);
  }

  public function renderFieldValue($value) {
    $value = $this->inflateReviewers($value);

    $phid_list = array();
    $suffix_map = array();
    foreach ($value as $reviewer) {
      $phid = $reviewer['phid'];
      $phid_list[] = $phid;
      if (isset($reviewer['suffixes']['!'])) {
        $suffix_map[$phid] = '!';
      }
    }

    return $this->renderHandleList($phid_list, $suffix_map);
  }

  public function getFieldTransactions($value) {
    $value = $this->inflateReviewers($value);

    $reviewer_list = array();
    foreach ($value as $reviewer) {
      $phid = $reviewer['phid'];
      if (isset($reviewer['suffixes']['!'])) {
        $reviewer_list[] = 'blocking('.$phid.')';
      } else {
        $reviewer_list[] = $phid;
      }
    }

    $xaction_key = DifferentialRevisionReviewersTransaction::EDITKEY;
    $xaction_type = "{$xaction_key}.set";

    return array(
      array(
        'type' => $xaction_type,
        'value' => $reviewer_list,
      ),
    );
  }

  private function flattenReviewers(array $values) {
    // NOTE: For now, `arc` relies on this field returning only scalars, so we
    // need to reduce the results into scalars. See T10981.
    $result = array();

    foreach ($values as $value) {
      $result[] = $value['phid'].implode('', array_keys($value['suffixes']));
    }

    return $result;
  }

  private function inflateReviewers(array $values) {
    $result = array();

    foreach ($values as $value) {
      if (substr($value, -1) == '!') {
        $value = substr($value, 0, -1);
        $suffixes = array('!' => '!');
      } else {
        $suffixes = array();
      }

      $result[] = array(
        'phid' => $value,
        'suffixes' => $suffixes,
      );
    }

    return $result;
  }

}
