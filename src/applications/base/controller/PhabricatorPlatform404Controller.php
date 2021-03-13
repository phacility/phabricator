<?php

final class PhabricatorPlatform404Controller
  extends PhabricatorController {

  public function processRequest() {
    return new Aphront404Response();
  }

}
