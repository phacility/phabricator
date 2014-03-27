<?php

final class HarbormasterStepEditController
  extends HarbormasterController {

  private $id;
  private $planID;
  private $className;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->planID = idx($data, 'plan');
    $this->className = idx($data, 'class');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    if ($this->id) {
      $step = id(new HarbormasterBuildStepQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->executeOne();
      if (!$step) {
        return new Aphront404Response();
      }
      $plan = $step->getBuildPlan();

      $is_new = false;
    } else {
      $plan = id(new HarbormasterBuildPlanQuery())
          ->setViewer($viewer)
          ->withIDs(array($this->planID))
          ->executeOne();
      if (!$plan) {
        return new Aphront404Response();
      }

      $impl = HarbormasterBuildStepImplementation::getImplementation(
        $this->className);
      if (!$impl) {
        return new Aphront404Response();
      }

      $step = HarbormasterBuildStep::initializeNewStep($viewer)
        ->setBuildPlanPHID($plan->getPHID())
        ->setClassName($this->className);

      $is_new = true;
    }

    $plan_uri = $this->getApplicationURI('plan/'.$plan->getID().'/');

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

      if ($is_new) {
        // This is okay, but a little iffy. We should move it inside the editor
        // if we create plans elsewhere.
        $steps = $plan->loadOrderedBuildSteps();
        $step->setSequence(count($steps) + 1);
      }

      try {
        $editor->applyTransactions($step, $xactions);
        return id(new AphrontRedirectResponse())->setURI($plan_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $field_list->appendFieldsToForm($form);

    if ($is_new) {
      $submit = pht('Create Build Step');
      $header = pht('New Step: %s', $implementation->getName());
      $crumb = pht('Add Step');
    } else {
      $submit = pht('Save Build Step');
      $header = pht('Edit Step: %s', $implementation->getName());
      $crumb = pht('Edit Step');
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue($submit)
        ->addCancelButton($plan_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setValidationException($validation_exception)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $id = $plan->getID();
    $crumbs->addTextCrumb(pht('Plan %d', $id), $plan_uri);
    $crumbs->addTextCrumb($crumb);

    $variables = $this->renderBuildVariablesTable();

    if ($is_new) {
      $xaction_view = null;
    } else {
      $xactions = id(new HarbormasterBuildStepTransactionQuery())
        ->setViewer($viewer)
        ->withObjectPHIDs(array($step->getPHID()))
        ->execute();

      $xaction_view = id(new PhabricatorApplicationTransactionView())
        ->setUser($viewer)
        ->setObjectPHID($step->getPHID())
        ->setTransactions($xactions)
        ->setShouldTerminate(true);
    }

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
