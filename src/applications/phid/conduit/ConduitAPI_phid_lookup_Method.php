<?php

/**
 * @group conduit
 */
final class ConduitAPI_phid_lookup_Method
  extends ConduitAPI_phid_Method {

  public function getMethodDescription() {
    return "Look up objects by name.";
  }

  public function defineParamTypes() {
    return array(
      'names' => 'required list<string>',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $names = $request->getValue('names');
    $phids = array();
    foreach ($names as $name) {
      $phid = PhabricatorPHID::fromObjectName($name, $request->getUser());
      if ($phid) {
        $phids[$name] = $phid;
      }
    }

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $result = array();
    foreach ($phids as $name => $phid) {
      if (isset($handles[$phid]) && $handles[$phid]->isComplete()) {
        $result[$name] = $this->buildHandleInformationDictionary(
          $handles[$phid]);
      }
    }

    return $result;
  }

}
