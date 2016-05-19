<?php

final class PhabricatorAuthSSHKeyListController
  extends PhabricatorAuthSSHKeyController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $object_phid = $request->getURIData('forPHID');
    $object = $this->loadSSHKeyObject($object_phid, false);
    if (!$object) {
      return new Aphront404Response();
    }

    $engine = id(new PhabricatorAuthSSHKeySearchEngine())
      ->setSSHKeyObject($object);

    return id($engine)
      ->setController($this)
      ->buildResponse();
  }

}
