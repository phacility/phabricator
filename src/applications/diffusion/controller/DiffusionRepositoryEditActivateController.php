<?php

final class DiffusionRepositoryEditActivateController
  extends DiffusionRepositoryEditController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($repository->getID()))
      ->executeOne();

    if (!$repository) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    if ($request->isFormPost()) {
      $xaction = id(new PhabricatorRepositoryTransaction())
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_ACTIVATE)
        ->setNewValue(!$repository->isTracked());

      $editor = id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, array($xaction));

      return id(new AphrontReloadResponse())->setURI($edit_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer);

    if ($repository->isTracked()) {
      $dialog
        ->setTitle(pht('Deactivate Repository?'))
        ->appendChild(
          pht('Deactivate this repository?'))
        ->addSubmitButton(pht('Deactivate Repository'))
        ->addCancelButton($edit_uri);
    } else {
      $dialog
        ->setTitle(pht('Activate Repository?'))
        ->appendChild(
          pht('Activate this repository?'))
        ->addSubmitButton(pht('Activate Repository'))
        ->addCancelButton($edit_uri);
    }

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }


}
