<?php

final class DiffusionRepositoryTestAutomationController
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

    if (!$repository->canPerformAutomation()) {
      return $this->newDialog()
        ->setTitle(pht('Automation Not Configured'))
        ->appendParagraph(
          pht(
            'You can not run a configuration test for this repository '.
            'because you have not configured repository automation yet. '.
            'Configure it first, then test the configuration.'))
        ->addCancelButton($edit_uri);
    }

    if ($request->isFormPost()) {
      $op = new DrydockTestRepositoryOperation();

      $operation = DrydockRepositoryOperation::initializeNewOperation($op)
        ->setAuthorPHID($viewer->getPHID())
        ->setObjectPHID($repository->getPHID())
        ->setRepositoryPHID($repository->getPHID())
        ->setRepositoryTarget('none:')
        ->save();

      $operation->scheduleUpdate();

      $operation_id = $operation->getID();
      $operation_uri = "/drydock/operation/{$operation_id}/";

      return id(new AphrontRedirectResponse())
        ->setURI($operation_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Test Automation Configuration'))
      ->appendParagraph(
        pht(
          'This configuration test will build a working copy of the '.
          'repository and perform some basic validation. If it works, '.
          'your configuration is substantially correct.'))
      ->appendParagraph(
        pht(
          'The test will not perform any writes against the repository, so '.
          'write operations may still fail even if the test passes. This '.
          'test covers building and reading working copies, but not writing '.
          'to them.'))
      ->appendParagraph(
        pht(
          'If you run into write failures despite passing this test, '.
          'it suggests that your setup is nearly correct but authentication '.
          'is probably not fully configured.'))
      ->addCancelButton($edit_uri)
      ->addSubmitButton(pht('Start Test'));
  }

}
