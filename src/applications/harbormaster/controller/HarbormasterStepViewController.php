<?php

final class HarbormasterStepViewController
  extends HarbormasterPlanController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $step = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$step) {
      return new Aphront404Response();
    }
    $plan = $step->getBuildPlan();

    $plan_id = $plan->getID();
    $plan_uri = $this->getApplicationURI("plan/{$plan_id}/");

    $field_list = PhabricatorCustomField::getObjectFields(
      $step,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($step);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Plan %d', $plan_id), $plan_uri);
    $crumbs->addTextCrumb(pht('Step %d', $id));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Build Step %d: %s', $id, $step->getName()))
      ->setHeaderIcon('fa-chevron-circle-right');

    $properties = $this->buildPropertyList($step, $field_list);
    $curtain = $this->buildCurtainView($step);

    $timeline = $this->buildTransactionTimeline(
      $step,
      new HarbormasterBuildStepTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $properties,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle(pht('Step %d', $id))
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function buildPropertyList(
    HarbormasterBuildStep $step,
    PhabricatorCustomFieldList $field_list) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    try {
      $implementation = $step->getStepImplementation();
    } catch (Exception $ex) {
      $implementation = null;
    }

    if ($implementation) {
      $type = $implementation->getName();
    } else {
      $type = phutil_tag(
        'em',
        array(),
        pht(
          'Invalid Implementation ("%s")!',
          $step->getClassName()));
    }

    $view->addProperty(pht('Step Type'), $type);

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($step->getDateCreated(), $viewer));

    $field_list->appendFieldsToPropertyList(
      $step,
      $viewer,
      $view);

    $description = $step->getDescription();
    if (strlen($description)) {
      $view->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent(
        new PHUIRemarkupView($viewer, $description));
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Properties'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);
  }


  private function buildCurtainView(HarbormasterBuildStep $step) {
    $viewer = $this->getViewer();
    $id = $step->getID();

    $curtain = $this->newCurtainView($step);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $step,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Step'))
        ->setHref($this->getApplicationURI("step/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit)
        ->setIcon('fa-pencil'));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete Step'))
        ->setHref($this->getApplicationURI("step/delete/{$id}/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setIcon('fa-times'));

    return $curtain;
  }


}
