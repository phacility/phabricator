<?php

final class Phabricator404Controller extends PhabricatorController {

  public function processRequest() {
    return new Aphront404Response();
  }

}
