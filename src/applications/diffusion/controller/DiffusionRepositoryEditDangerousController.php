<?php

final class DiffusionRepositoryEditDangerousController
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

    if (!$repository->canAllowDangerousChanges()) {
      return $this->newDialog()
        ->setTitle(pht('Unprotectable Repository'))
        ->appendParagraph(
          pht(
            'This repository can not be protected from dangerous changes '.
            'because Phabricator does not control what users are allowed '.
            'to push to it.'))
        ->addCancelButton($panel_uri);
    }

    if ($request->isFormPost()) {
      $xaction = id(new PhabricatorRepositoryTransaction())
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_DANGEROUS)
        ->setNewValue(!$repository->shouldAllowDangerousChanges());

      $editor = id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, array($xaction));

      return id(new AphrontReloadResponse())->setURI($panel_uri);
    }

    $force = phutil_tag('tt', array(), '--force');

    if ($repository->shouldAllowDangerousChanges()) {
      $title = pht('Prevent Dangerous Changes');

      if ($repository->isSVN()) {
        $body = pht(
          'It will no longer be possible to edit revprops in this '.
          'repository.');
      } else {
        $body = pht(
          'It will no longer be possible to delete branches from this '.
          'repository, or %s push to this repository.',
          $force);
      }

      $submit = pht('Prevent Dangerous Changes');
    } else {
      $title = pht('Allow Dangerous Changes');
      if ($repository->isSVN()) {
        $body = pht(
          'If you allow dangerous changes, it will be possible to edit '.
          'reprops in this repository, including arbitrarily rewriting '.
          'commit messages. These operations can alter a repository in a '.
          'way that is difficult to recover from.');
      } else {
        $body = pht(
          'If you allow dangerous changes, it will be possible to delete '.
          'branches and %s push this repository. These operations can '.
          'alter a repository in a way that is difficult to recover from.',
          $force);
      }
      $submit = pht('Allow Dangerous Changes');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($submit)
      ->addCancelButton($panel_uri);
  }

}
