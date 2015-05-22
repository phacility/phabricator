<?php

final class HarbormasterStepEditController extends HarbormasterController {

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
      HarbormasterManagePlansCapability::CAPABILITY);

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

    $e_name = true;
    $v_name = $step->getName();
    $e_description = true;
    $v_description = $step->getDescription();
    $e_depends_on = true;
    $v_depends_on = $step->getDetail('dependsOn', array());

    $errors = array();
    $validation_exception = null;
    if ($request->isFormPost()) {
      $e_name = null;
      $v_name = $request->getStr('name');
      $e_description = null;
      $v_description = $request->getStr('description');
      $e_depends_on = null;
      $v_depends_on = $request->getArr('dependsOn');

      $xactions = $field_list->buildFieldTransactionsFromRequest(
        new HarbormasterBuildStepTransaction(),
        $request);

      $editor = id(new HarbormasterBuildStepEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request);

      $name_xaction = id(new HarbormasterBuildStepTransaction())
        ->setTransactionType(HarbormasterBuildStepTransaction::TYPE_NAME)
        ->setNewValue($v_name);
      array_unshift($xactions, $name_xaction);

      $depends_on_xaction = id(new HarbormasterBuildStepTransaction())
        ->setTransactionType(
          HarbormasterBuildStepTransaction::TYPE_DEPENDS_ON)
        ->setNewValue($v_depends_on);
      array_unshift($xactions, $depends_on_xaction);

      $description_xaction = id(new HarbormasterBuildStepTransaction())
        ->setTransactionType(
          HarbormasterBuildStepTransaction::TYPE_DESCRIPTION)
        ->setNewValue($v_description);
      array_unshift($xactions, $description_xaction);

      if ($is_new) {
        // When creating a new step, make sure we have a create transaction
        // so we'll apply the transactions even if the step has no
        // configurable options.
        $create_xaction = id(new HarbormasterBuildStepTransaction())
          ->setTransactionType(HarbormasterBuildStepTransaction::TYPE_CREATE);
        array_unshift($xactions, $create_xaction);
      }

      try {
        $editor->applyTransactions($step, $xactions);
        return id(new AphrontRedirectResponse())->setURI($plan_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setError($e_name)
          ->setValue($v_name));

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(id(new HarbormasterBuildDependencyDatasource())
            ->setParameters(array(
              'planPHID' => $plan->getPHID(),
              'stepPHID' => $is_new ? null : $step->getPHID(),
            )))
          ->setName('dependsOn')
          ->setLabel(pht('Depends On'))
          ->setError($e_depends_on)
          ->setValue($v_depends_on));

    $field_list->appendFieldsToForm($form);

    $form
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setError($e_description)
          ->setValue($v_description));

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
      $timeline = null;
    } else {
      $timeline = $this->buildTransactionTimeline(
        $step,
        new HarbormasterBuildStepTransactionQuery());
      $timeline->setShouldTerminate(true);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $variables,
        $timeline,
      ),
      array(
        'title' => $implementation->getName(),
      ));
  }

  private function renderBuildVariablesTable() {
    $viewer = $this->getRequest()->getUser();

    $variables = HarbormasterBuild::getAvailableBuildVariables();
    ksort($variables);

    $rows = array();
    $rows[] = pht(
      'The following variables can be used in most fields. '.
      'To reference a variable, use `%s` in a field.',
      '${name}');
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
