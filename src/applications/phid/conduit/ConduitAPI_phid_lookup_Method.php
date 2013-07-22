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

    $query = id(new PhabricatorObjectQuery())
      ->setViewer($request->getUser())
      ->withNames($names);
    $query->execute();
    $name_map = $query->getNamedResults();

    $handles = id(new PhabricatorObjectHandleData(mpull($name_map, 'getPHID')))
      ->setViewer($request->getUser())
      ->loadHandles();

    $result = array();
    foreach ($name_map as $name => $object) {
      $phid = $object->getPHID();
      $handle = $handles[$phid];
      $result[$name] = $this->buildHandleInformationDictionary($handle);
    }

    return $result;
  }

}
