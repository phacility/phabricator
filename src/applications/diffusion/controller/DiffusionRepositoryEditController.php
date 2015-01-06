<?php

abstract class DiffusionRepositoryEditController
  extends DiffusionController {

  protected function buildApplicationCrumbs($is_main = false) {
    $crumbs = parent::buildApplicationCrumbs();

    if ($this->diffusionRequest) {
      $repository = $this->getDiffusionRequest()->getRepository();
      $repo_uri = $this->getRepositoryControllerURI($repository, '');
      $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

      $crumbs->addTextCrumb('r'.$repository->getCallsign(), $repo_uri);

      if ($is_main) {
        $crumbs->addTextCrumb(pht('Edit Repository'));
      } else {
        $crumbs->addTextCrumb(pht('Edit'), $edit_uri);
      }
    }

    return $crumbs;
  }

}
