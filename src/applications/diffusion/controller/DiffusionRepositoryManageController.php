<?php

abstract class DiffusionRepositoryManageController
  extends DiffusionController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    if ($this->hasDiffusionRequest()) {
      $drequest = $this->getDiffusionRequest();
      $repository = $drequest->getRepository();

      $crumbs->addTextCrumb(
        $repository->getDisplayName(),
        $repository->getURI());

      $crumbs->addTextCrumb(
        pht('Manage'),
        $repository->getPathURI('manage/'));
    }

    return $crumbs;
  }

  public function newBox($title, $content, $action = null) {
    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    if ($action) {
      $header->addActionItem($action);
    }

    $view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($content)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG);

    return $view;
  }

}
