<?php

final class DiffusionRepositoryEditDangerousController
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

    if (!$repository->canAllowDangerousChanges()) {
      if ($repository->isSVN()) {
        return $this->newDialog()
          ->setTitle(pht('Not in Danger'))
          ->appendParagraph(
            pht(
              'It is not possible for users to push any dangerous changes '.
              'to a Subversion repository. Pushes to a Subversion repository '.
              'can always be reverted and never destroy data.'))
          ->addCancelButton($edit_uri);
      } else {
        return $this->newDialog()
          ->setTitle(pht('Unprotectable Repository'))
          ->appendParagraph(
            pht(
              'This repository can not be protected from dangerous changes '.
              'because Phabricator does not control what users are allowed '.
              'to push to it.'))
          ->addCancelButton($edit_uri);
      }
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

      return id(new AphrontReloadResponse())->setURI($edit_uri);
    }

    $force = phutil_tag('tt', array(), '--force');

    if ($repository->shouldAllowDangerousChanges()) {
      return $this->newDialog()
        ->setTitle(pht('Prevent Dangerous changes?'))
        ->appendChild(
          pht(
            'It will no longer be possible to delete branches from this '.
            'repository, or %s push to this repository.',
            $force))
        ->addSubmitButton(pht('Prevent Dangerous Changes'))
        ->addCancelButton($edit_uri);
    } else {
      return $this->newDialog()
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
  }

}
