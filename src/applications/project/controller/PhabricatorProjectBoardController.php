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

  final private function newViewState() {
    $project = $this->getProject();
    $request = $this->getRequest();

    return id(new PhabricatorWorkboardViewState())
      ->setProject($project)
      ->readFromRequest($request);
  }

}
