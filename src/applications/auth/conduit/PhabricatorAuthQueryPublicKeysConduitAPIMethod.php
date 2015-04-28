<?php

final class PhabricatorAuthQueryPublicKeysConduitAPIMethod
  extends PhabricatorAuthConduitAPIMethod {

  public function getAPIMethodName() {
    return 'auth.querypublickeys';
  }

  public function getMethodDescription() {
    return pht('Query public keys.');
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional list<id>',
      'objectPHIDs' => 'optional list<phid>',
      'keys' => 'optional list<string>',
    ) + self::getPagerParamTypes();
  }

  protected function defineReturnType() {
    return 'result-set';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $query = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($viewer);

    $ids = $request->getValue('ids');
    if ($ids !== null) {
      $query->withIDs($ids);
    }

    $object_phids = $request->getValue('objectPHIDs');
    if ($object_phids !== null) {
      $query->withObjectPHIDs($object_phids);
    }

    $keys = $request->getValue('keys');
    if ($keys !== null) {
      $key_objects = array();
      foreach ($keys as $key) {
        $key_objects[] = PhabricatorAuthSSHPublicKey::newFromRawKey($key);
      }

      $query->withKeys($key_objects);
    }

    $pager = $this->newPager($request);
    $public_keys = $query->executeWithCursorPager($pager);

    $data = array();
    foreach ($public_keys as $public_key) {
      $data[] = array(
        'id' => $public_key->getID(),
        'name' => $public_key->getName(),
        'objectPHID' => $public_key->getObjectPHID(),
        'isTrusted' => (bool)$public_key->getIsTrusted(),
        'key' => $public_key->getEntireKey(),
      );
    }

    $results = array(
      'data' => $data,
    );

    return $this->addPagerResults($results, $pager);
  }

}
