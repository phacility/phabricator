<?php

final class DiffusionMirrorDeleteController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $mirror = id(new PhabricatorRepositoryMirrorQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$mirror) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/#mirrors');

    if ($request->isFormPost()) {
      $mirror->delete();
      return id(new AphrontReloadResponse())->setURI($edit_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Really delete mirror?'))
      ->appendChild(
        pht('Phabricator will stop pushing updates to this mirror.'))
      ->addSubmitButton(pht('Delete Mirror'))
      ->addCancelButton($edit_uri);
  }


}
