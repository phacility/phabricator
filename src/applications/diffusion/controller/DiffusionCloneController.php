<?php

final class DiffusionCloneController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $display_never = PhabricatorRepositoryURI::DISPLAY_NEVER;
    $warning = null;

    $uris = $repository->getURIs();
    foreach ($uris as $uri) {
      if ($uri->getIsDisabled()) {
        continue;
      }

      if ($uri->getEffectiveDisplayType() == $display_never) {
        continue;
      }

      if ($repository->isSVN()) {
        $label = phutil_tag_div('diffusion-clone-label', pht('Checkout'));
      } else {
        $label = phutil_tag_div('diffusion-clone-label', pht('Clone'));
      }

      $view->addProperty(
        $label,
        $this->renderCloneURI($repository, $uri));
    }

    if (!$view->hasAnyProperties()) {
      $view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(pht('Repository has no URIs set.'));
    }

    $info = null;

    // Try to load alternatives. This may fail for repositories which have not
    // cloned yet. If it does, just ignore it and continue.
    try {
      $alternatives = $drequest->getRefAlternatives();
    } catch (ConduitClientException $ex) {
      $alternatives = array();
    }

    if ($alternatives) {
      $message = array(
        pht(
          'The ref "%s" is ambiguous in this repository.',
          $drequest->getBranch()),
        ' ',
        phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'refs',
              )),
          ),
          pht('View Alternatives')),
      );

      $messages = array($message);

      $warning = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(array($message));
    }

    $cancel_uri = $drequest->generateURI(
      array(
        'action' => 'branch',
        'path' => '/',
      ));

    return $this->newDialog()
      ->setTitle(pht('Clone Repository'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addCancelButton($cancel_uri, pht('Close'))
      ->appendChild(array($view, $warning));
  }

  private function renderCloneURI(
    PhabricatorRepository $repository,
    PhabricatorRepositoryURI $uri) {

    if ($repository->isSVN()) {
      $display = csprintf(
        'svn checkout %R %R',
        (string)$uri->getDisplayURI(),
        $repository->getCloneName());
    } else {
      $display = csprintf('%R', (string)$uri->getDisplayURI());
    }

    $display = (string)$display;
    $viewer = $this->getViewer();

    return id(new DiffusionCloneURIView())
      ->setViewer($viewer)
      ->setRepository($repository)
      ->setRepositoryURI($uri)
      ->setDisplayURI($display);
  }

}
