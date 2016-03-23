<?php

abstract class DiffusionRepositoryEditController
  extends DiffusionController {

  protected function buildApplicationCrumbs($is_main = false) {
    $crumbs = parent::buildApplicationCrumbs();

    if ($this->hasDiffusionRequest()) {
      $drequest = $this->getDiffusionRequest();
      $repository = $drequest->getRepository();
      $repo_uri = $repository->getURI();
      $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

      $crumbs->addTextCrumb($repository->getDisplayname(), $repo_uri);

      if ($is_main) {
        $crumbs->addTextCrumb(pht('Edit Repository'));
      } else {
        $crumbs->addTextCrumb(pht('Edit'), $edit_uri);
      }
    }
    $crumbs->setBorder(true);

    return $crumbs;
  }

}
