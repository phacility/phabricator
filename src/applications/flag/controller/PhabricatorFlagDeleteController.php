<?php

final class PhabricatorFlagDeleteController extends PhabricatorFlagController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $flag = id(new PhabricatorFlag())->load($this->id);
    if (!$flag) {
      return new Aphront404Response();
    }

    if ($flag->getOwnerPHID() != $user->getPHID()) {
      return new Aphront400Response();
    }

    $flag->delete();

    return id(new AphrontReloadResponse())->setURI('/flag/');
  }

}
