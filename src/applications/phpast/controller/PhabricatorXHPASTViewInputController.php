<?php

final class PhabricatorXHPASTViewInputController
  extends PhabricatorXHPASTViewPanelController {

  public function handleRequest(AphrontRequest $request) {
    $input = $this->getStorageTree()->getInput();
    return $this->buildXHPASTViewPanelResponse($input);
  }
}
