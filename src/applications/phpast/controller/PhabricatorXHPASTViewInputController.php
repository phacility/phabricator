<?php

final class PhabricatorXHPASTViewInputController
  extends PhabricatorXHPASTViewPanelController {

  public function processRequest() {
    $input = $this->getStorageTree()->getInput();
    return $this->buildXHPASTViewPanelResponse($input);
  }
}
