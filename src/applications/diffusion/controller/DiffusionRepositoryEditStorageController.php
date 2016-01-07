<?php

final class DiffusionRepositoryEditStorageController
  extends DiffusionRepositoryEditController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_local = $repository->getHumanReadableDetail('local-path');
    $errors = array();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Storage'));

    $title = pht('Edit %s', $repository->getName());

    $service_phid = $repository->getAlmanacServicePHID();
    if ($service_phid) {
      $handles = $this->loadViewerHandles(array($service_phid));
      $v_service = $handles[$service_phid]->renderLink();
    } else {
      $v_service = phutil_tag(
        'em',
        array(),
        pht('Local'));
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Storage Service'))
          ->setValue($v_service))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setName('local')
          ->setLabel(pht('Storage Path'))
          ->setValue($v_local))
      ->appendRemarkupInstructions(
        pht(
          "You can not adjust the local path for this repository from the ".
          "web interface. To edit it, run this command:\n\n  %s",
          sprintf(
            'phabricator/ $ ./bin/repository edit %s --as %s --local-path ...',
            $repository->getMonogram(),
            $viewer->getUsername())))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($edit_uri, pht('Done')));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form)
      ->setFormErrors($errors);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($object_box);
  }

}
