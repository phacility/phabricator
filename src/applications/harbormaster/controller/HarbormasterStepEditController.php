<?php

final class HarbormasterStepEditController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $step = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$step) {
      return new Aphront404Response();
    }

    $plan = $step->getBuildPlan();

    $implementation = $step->getStepImplementation();

    $field_list = PhabricatorCustomField::getObjectFields(
      $step,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($step);

    $errors = array();
    $validation_exception = null;
    if ($request->isFormPost()) {
      $xactions = $field_list->buildFieldTransactionsFromRequest(
        new HarbormasterBuildStepTransaction(),
        $request);

      $editor = id(new HarbormasterBuildStepEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request);

      try {
        $editor->applyTransactions($step, $xactions);
        return id(new AphrontRedirectResponse())
          ->setURI($this->getApplicationURI('plan/'.$plan->getID().'/'));
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $field_list->appendFieldsToForm($form);

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Build Step'))
        ->addCancelButton(
          $this->getApplicationURI('plan/'.$plan->getID().'/')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Edit Step: '.$implementation->getName())
      ->setValidationException($validation_exception)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $id = $plan->getID();
    $crumbs->addTextCrumb(
      pht("Plan %d", $id),
      $this->getApplicationURI("plan/{$id}/"));
    $crumbs->addTextCrumb(pht('Edit Step'));

    $variables = $this->renderBuildVariablesTable();

    $xactions = id(new HarbormasterBuildStepTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($step->getPHID()))
      ->execute();

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($step->getPHID())
      ->setTransactions($xactions)
      ->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $variables,
        $xaction_view,
      ),
      array(
        'title' => $implementation->getName(),
        'device' => true,
      ));
  }

  private function renderBuildVariablesTable() {
    $viewer = $this->getRequest()->getUser();

    $variables = HarbormasterBuild::getAvailableBuildVariables();
    ksort($variables);

    $rows = array();
    $rows[] = pht(
      'The following variables can be used in most fields. To reference '.
      'a variable, use `${name}` in a field.');
    $rows[] = pht('| Variable | Description |');
    $rows[] = '|---|---|';
    foreach ($variables as $name => $description) {
      $rows[] = '| `'.$name.'` | '.$description.' |';
    }
    $rows = implode("\n", $rows);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions($rows);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Build Variables'))
      ->appendChild($form);
  }


}
