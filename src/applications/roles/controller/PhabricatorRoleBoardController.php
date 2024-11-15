<?php

abstract class PhabricatorRoleBoardController
  extends PhabricatorRoleController {

  private $viewState;

  final protected function getViewState() {
    if ($this->viewState === null) {
      $this->viewState = $this->newViewState();
    }

    return $this->viewState;
  }

  private function newViewState() {
    $role = $this->getRole();
    $request = $this->getRequest();

    return id(new PhabricatorRoleWorkboardViewState())
      ->setRole($role)
      ->readFromRequest($request);
  }

  final protected function newWorkboardDialog() {
    $dialog = $this->newDialog();

    $state = $this->getViewState();
    foreach ($state->getQueryParameters() as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    return $dialog;
  }

}
