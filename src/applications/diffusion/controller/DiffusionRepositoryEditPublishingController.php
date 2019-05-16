<?php

final class DiffusionRepositoryEditPublishingController
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
      if ($repository->isPublishingDisabled()) {
        $new_status = true;
      } else {
        $new_status = false;
      }

      $xaction = id(new PhabricatorRepositoryTransaction())
        ->setTransactionType(
          PhabricatorRepositoryNotifyTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_status);

      $editor = id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, array($xaction));

      return id(new AphrontReloadResponse())->setURI($panel_uri);
    }

    $body = array();
    if (!$repository->isPublishingDisabled()) {
      $title = pht('Disable Publishing');
      $body[] = pht(
        'If you disable publishing for this repository, new commits '.
        'will not: send email, publish feed stories, trigger audits, or '.
        'trigger Herald.');

      $body[] = pht(
        'This option is most commonly used to temporarily allow a major '.
        'repository maintenance operation (like a history rewrite) to '.
        'occur with minimal disruption to users.');

      $submit = pht('Disable Publishing');
    } else {
      $title = pht('Reactivate Publishing');
      $body[] = pht(
        'If you reactivate publishing for this repository, new commits '.
        'that become reachable from permanent refs will: send email, '.
        'publish feed stories, trigger audits, and trigger Herald.');

      $body[] = pht(
        'Commits which became reachable from a permanent ref while '.
        'publishing was disabled will not trigger these actions '.
        'retroactively.');

      $submit = pht('Reactivate Publishing');
    }

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->addSubmitButton($submit)
      ->addCancelButton($panel_uri);

    foreach ($body as $graph) {
      $dialog->appendParagraph($graph);
    }

    return $dialog;
  }

}
