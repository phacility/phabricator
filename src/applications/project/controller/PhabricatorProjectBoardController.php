<?php

abstract class PhabricatorProjectBoardController
  extends PhabricatorProjectController {

  private $viewState;

  final protected function getViewState() {
    if ($this->viewState === null) {
      $this->viewState = $this->newViewState();
    }

    return $this->viewState;
  }

  private function newViewState() {
    $project = $this->getProject();
    $request = $this->getRequest();

    return id(new PhabricatorWorkboardViewState())
      ->setProject($project)
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
