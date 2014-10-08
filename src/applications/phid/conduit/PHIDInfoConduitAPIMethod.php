<?php

final class PHIDInfoConduitAPIMethod extends PHIDConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phid.info';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Replaced by 'phid.query'.";
  }

  public function getMethodDescription() {
    return 'Retrieve information about an arbitrary PHID.';
  }

  public function defineParamTypes() {
    return array(
      'phid' => 'required phid',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-PHID' => 'No such object exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $phid = $request->getValue('phid');

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($request->getUser())
      ->withPHIDs(array($phid))
      ->executeOne();

    if (!$handle->isComplete()) {
      throw new ConduitException('ERR-BAD-PHID');
    }

    return $this->buildHandleInformationDictionary($handle);
  }

}
