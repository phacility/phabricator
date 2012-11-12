<?php

final class PhabricatorFileShortcutController
  extends PhabricatorFileController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $file = id(new PhabricatorFile())->load($this->id);
    if (!$file) {
      return new Aphront404Response();
    }
    return id(new AphrontRedirectResponse())->setURI($file->getBestURI());
  }

}
