<?php

/**
 * @group conduit
 */
final class ConduitAPI_phid_query_Method
  extends ConduitAPI_phid_Method {

  public function getMethodDescription() {
    return "Retrieve information about arbitrary PHIDs.";
  }

  public function defineParamTypes() {
    return array(
      'phids' => 'required list<phid>',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {

    $phids = $request->getValue('phids');

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $result = array();
    foreach ($handles as $phid => $handle) {
      if ($handle->isComplete()) {
        $result[$phid] = $this->buildHandleInformationDictionary($handle);
      }
    }

    return $result;
  }

}
