<?php

abstract class DiffusionRepositoryEditController
  extends DiffusionController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    if ($this->diffusionRequest) {
      $repository = $this->getDiffusionRequest()->getRepository();
      $repo_uri = $this->getRepositoryControllerURI($repository, '');
      $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('r'.$repository->getCallsign())
          ->setHref($repo_uri));

      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Edit'))
          ->setHref($edit_uri));
    }

    return $crumbs;
  }

}
