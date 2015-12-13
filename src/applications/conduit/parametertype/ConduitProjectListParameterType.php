<?php

final class ConduitProjectListParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key) {
    $list = parent::getParameterValue($request, $key);
    $list = $this->validateStringList($request, $key, $list);
    return id(new PhabricatorProjectPHIDResolver())
      ->setViewer($this->getViewer())
      ->resolvePHIDs($list);
  }

  protected function getParameterTypeName() {
    return 'list<project>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('List of project PHIDs.'),
      pht('List of project tags.'),
      pht('List with a mixture of PHIDs and tags.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '["PHID-PROJ-1111"]',
      '["backend"]',
      '["PHID-PROJ-2222", "frontend"]',
    );
  }

}
