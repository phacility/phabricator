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

}
