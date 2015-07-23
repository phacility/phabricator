<?php

final class FlagQueryConduitAPIMethod extends FlagConduitAPIMethod {

  public function getAPIMethodName() {
    return 'flag.query';
  }

  public function getMethodDescription() {
    return pht('Query flag markers.');
  }

  protected function defineParamTypes() {
    return array(
      'ownerPHIDs'    => 'optional list<phid>',
      'types'         => 'optional list<type>',
      'objectPHIDs'   => 'optional list<phid>',

      'offset'        => 'optional int',
      'limit'         => 'optional int (default = 100)',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = new PhabricatorFlagQuery();
    $query->setViewer($request->getUser());

    $owner_phids = $request->getValue('ownerPHIDs', array());
    if ($owner_phids) {
      $query->withOwnerPHIDs($owner_phids);
    }

    $object_phids = $request->getValue('objectPHIDs', array());
    if ($object_phids) {
      $query->withObjectPHIDs($object_phids);
    }

    $types = $request->getValue('types', array());
    if ($types) {
      $query->withTypes($types);
    }

    $query->needHandles(true);

    $query->setOffset($request->getValue('offset', 0));
    $query->setLimit($request->getValue('limit', 100));

    $flags = $query->execute();

    $results = array();
    foreach ($flags as $flag) {
      $results[] = $this->buildFlagInfoDictionary($flag);
    }

    return $results;
  }

}
