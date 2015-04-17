<?php

final class PHIDQueryConduitAPIMethod extends PHIDConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phid.query';
  }

  public function getMethodDescription() {
    return 'Retrieve information about arbitrary PHIDs.';
  }

  protected function defineParamTypes() {
    return array(
      'phids' => 'required list<phid>',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $phids = $request->getValue('phids');

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($request->getUser())
      ->withPHIDs($phids)
      ->execute();

    $result = array();
    foreach ($handles as $phid => $handle) {
      if ($handle->isComplete()) {
        $result[$phid] = $this->buildHandleInformationDictionary($handle);
      }
    }

    return $result;
  }

}
