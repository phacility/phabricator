<?php

final class DifferentialReviewersCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'reviewerPHIDs';

  public function getFieldName() {
    return pht('Reviewers');
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
