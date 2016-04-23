<?php

final class DiffusionRepositoryEditActivateController
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

    if ($request->isFormPost()) {
      if (!$repository->isTracked()) {
        $new_status = PhabricatorRepository::STATUS_ACTIVE;
      } else {
        $new_status = PhabricatorRepository::STATUS_INACTIVE;
      }

      $xaction = id(new PhabricatorRepositoryTransaction())
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_ACTIVATE)
        ->setNewValue($new_status);

      $editor = id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, array($xaction));

      return id(new AphrontReloadResponse())->setURI($edit_uri);
    }

    if ($repository->isTracked()) {
      return $this->newDialog()
        ->setTitle(pht('Deactivate Repository?'))
        ->appendChild(
          pht('Deactivate this repository?'))
        ->addSubmitButton(pht('Deactivate Repository'))
        ->addCancelButton($edit_uri);
    } else {
      return $this->newDialog()
        ->setTitle(pht('Activate Repository?'))
        ->appendChild(
          pht('Activate this repository?'))
        ->addSubmitButton(pht('Activate Repository'))
        ->addCancelButton($edit_uri);
    }
  }

}
