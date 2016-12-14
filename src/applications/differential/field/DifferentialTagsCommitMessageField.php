<?php

final class DifferentialTagsCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'phabricator:projects';

  public function getFieldName() {
    return pht('Tags');
  }

  public function getFieldAliases() {
    return array(
      'Tag',
      'Project',
      'Projects',
    );
  }

  public function parseFieldValue($value) {
    return $this->parseObjectList(
      $value,
      array(
        PhabricatorProjectProjectPHIDType::TYPECONST,
      ));
  }

}
