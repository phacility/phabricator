<?php

final class DifferentialTasksCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'maniphestTaskPHIDs';

  public function getFieldName() {
    return pht('Maniphest Tasks');
  }

  public function getFieldOrder() {
    return 8000;
  }

  public function getFieldAliases() {
    return array(
      'Task',
      'Tasks',
      'Maniphest Task',
    );
  }

  public function isTemplateField() {
    return false;
  }

  public function parseFieldValue($value) {
    return $this->parseObjectList(
      $value,
      array(
        ManiphestTaskPHIDType::TYPECONST,
      ));
  }

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    if (!$revision->getPHID()) {
      return array();
    }

    $projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      DifferentialRevisionHasTaskEdgeType::EDGECONST);
    $projects = array_reverse($projects);

    return $projects;
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
        'type' => 'tasks.set',
        'value' => $value,
      ),
    );
  }
}
