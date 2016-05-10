<?php

final class DiffusionRepositoryEditActivateController
  extends DiffusionRepositoryManageController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $panel_uri = id(new DiffusionRepositoryBasicsManagementPanel())
      ->setRepository($repository)
      ->getPanelURI();

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

      return id(new AphrontReloadResponse())->setURI($panel_uri);
    }

    if ($repository->isTracked()) {
      $title = pht('Deactivate Repository');
      $body = pht(
        'If you deactivate this repository, it will no longer be updated. '.
        'Observation and mirroring will cease, and pushing and pulling will '.
        'be disabled. You can reactivate the repository later.');
      $submit = pht('Deactivate Repository');
    } else {
      $title = pht('Activate Repository');

      $is_new = $repository->isNewlyInitialized();
      if ($is_new) {
        if ($repository->isHosted()) {
          $body = pht(
            'This repository will become a new hosted repository. '.
            'It will begin serving read and write traffic.');
        } else {
          $body = pht(
            'This repository will observe an existing remote repository. '.
            'It will begin fetching changes from the remote.');
        }
      } else {
        $body = pht(
          'This repository will resume updates, observation, mirroring, '.
          'and serving any configured read and write traffic.');
      }

      $submit = pht('Activate Repository');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->addSubmitButton($submit)
      ->addCancelButton($panel_uri);
  }

}
