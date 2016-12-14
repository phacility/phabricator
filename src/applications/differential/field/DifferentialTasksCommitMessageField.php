<?php

final class DifferentialTasksCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'maniphestTaskPHIDs';

  public function getFieldName() {
    return pht('Maniphest Tasks');
  }

  public function getFieldAliases() {
    return array(
      'Task',
      'Tasks',
      'Maniphest Task',
    );
  }

  public function parseFieldValue($value) {
    return $this->parseObjectList(
      $value,
      array(
        ManiphestTaskPHIDType::TYPECONST,
      ));
  }

}
