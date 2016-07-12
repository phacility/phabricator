<?php

final class ConduitUserListParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key) {
    $list = parent::getParameterValue($request, $key);
    $list = $this->validateStringList($request, $key, $list);
    return id(new PhabricatorUserPHIDResolver())
      ->setViewer($this->getViewer())
      ->resolvePHIDs($list);
  }

  protected function getParameterTypeName() {
    return 'list<user>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('List of user PHIDs.'),
      pht('List of usernames.'),
      pht('List with a mixture of PHIDs and usernames.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '["PHID-USER-1111"]',
      '["alincoln"]',
      '["PHID-USER-2222", "alincoln"]',
    );
  }

}
