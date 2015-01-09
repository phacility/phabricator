<?php

final class DiffusionMirrorDeleteController
  extends DiffusionController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $drequest = $this->diffusionRequest;
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

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Really delete mirror?'))
      ->appendChild(
        pht('Phabricator will stop pushing updates to this mirror.'))
      ->addSubmitButton(pht('Delete Mirror'))
      ->addCancelButton($edit_uri);

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }


}
