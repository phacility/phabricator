<?php

final class DiffusionRepositoryEditEnormousController
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

    if (!$repository->canAllowEnormousChanges()) {
      return $this->newDialog()
        ->setTitle(pht('Unprotectable Repository'))
        ->appendParagraph(
          pht(
            'This repository can not be protected from enormous changes '.
            'because Phabricator does not control what users are allowed '.
            'to push to it.'))
        ->addCancelButton($panel_uri);
    }

    if ($request->isFormPost()) {
      $xaction = id(new PhabricatorRepositoryTransaction())
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_ENORMOUS)
        ->setNewValue(!$repository->shouldAllowEnormousChanges());

      $editor = id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, array($xaction));

      return id(new AphrontReloadResponse())->setURI($panel_uri);
    }

    if ($repository->shouldAllowEnormousChanges()) {
      $title = pht('Prevent Enormous Changes');

      $body = pht(
        'It will no longer be possible to push enormous changes to this '.
        'repository.');

      $submit = pht('Prevent Enormous Changes');
    } else {
      $title = pht('Allow Enormous Changes');

      $body = array(
        pht(
          'If you allow enormous changes, users can push commits which are '.
          'too large for Herald to process content rules for. This can allow '.
          'users to evade content rules implemented in Herald.'),
        pht(
          'You can selectively configure Herald by adding rules to prevent a '.
          'subset of enormous changes (for example, based on who is trying '.
          'to push the change).'),
      );

      $submit = pht('Allow Enormous Changes');
    }

    $more_help = pht(
      'Enormous changes are commits which are too large to process with '.
      'content rules because: the diff text for the change is larger than '.
      '%s bytes; or the diff text takes more than %s seconds to extract.',
      new PhutilNumber(HeraldCommitAdapter::getEnormousByteLimit()),
      new PhutilNumber(HeraldCommitAdapter::getEnormousTimeLimit()));

    $response = $this->newDialog();

      foreach ((array)$body as $paragraph) {
        $response->appendParagraph($paragraph);
      }

    return $response
      ->setTitle($title)
      ->appendParagraph($more_help)
      ->addSubmitButton($submit)
      ->addCancelButton($panel_uri);
  }

}
