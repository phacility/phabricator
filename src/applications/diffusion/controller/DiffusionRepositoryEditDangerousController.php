<?php

final class DiffusionRepositoryEditDangerousController
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

    if (!$repository->canAllowDangerousChanges()) {
      return new Aphront400Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    if ($request->isFormPost()) {
      $xaction = id(new PhabricatorRepositoryTransaction())
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_DANGEROUS)
        ->setNewValue(!$repository->shouldAllowDangerousChanges());

      $editor = id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, array($xaction));

      return id(new AphrontReloadResponse())->setURI($edit_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer);

    $force = phutil_tag('tt', array(), '--force');

    if ($repository->shouldAllowDangerousChanges()) {
      $dialog
        ->setTitle(pht('Prevent Dangerous changes?'))
        ->appendChild(
          pht(
            'It will no longer be possible to delete branches from this '.
            'repository, or %s push to this repository.',
            $force))
        ->addSubmitButton(pht('Prevent Dangerous Changes'))
        ->addCancelButton($edit_uri);
    } else {
      $dialog
        ->setTitle(pht('Allow Dangerous Changes?'))
        ->appendChild(
          pht(
            'If you allow dangerous changes, it will be possible to delete '.
            'branches and %s push this repository. These operations can '.
            'alter a repository in a way that is difficult to recover from.',
            $force))
        ->addSubmitButton(pht('Allow Dangerous Changes'))
        ->addCancelButton($edit_uri);
    }

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

}
