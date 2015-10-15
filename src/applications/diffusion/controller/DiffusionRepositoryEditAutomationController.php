<?php

final class DiffusionRepositoryEditAutomationController
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

    if (!$repository->supportsAutomation()) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_blueprints = $repository->getHumanReadableDetail(
      'automation.blueprintPHIDs');

    if ($request->isFormPost()) {
      $v_blueprints = $request->getArr('blueprintPHIDs');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_blueprints =
        PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_blueprints)
        ->setNewValue($v_blueprints);

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Automation'));

    $title = pht('Edit %s', $repository->getName());

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          "Configure **Repository Automation** to allow Phabricator to ".
          "write to this repository.".
          "\n\n".
          "IMPORTANT: This feature is new, experimental, and not supported. ".
          "Use it at your own risk."))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Use Blueprints'))
          ->setName('blueprintPHIDs')
          ->setValue($v_blueprints)
          ->setDatasource(new DrydockBlueprintDatasource()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save'))
          ->addCancelButton($edit_uri));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
