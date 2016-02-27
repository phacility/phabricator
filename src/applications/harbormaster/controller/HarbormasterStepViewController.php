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

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Build Step %d: %s', $id, $step->getName()));

    $properties = $this->buildPropertyList($step, $field_list);
    $actions = $this->buildActionList($step);
    $properties->setActionList($actions);

    $box->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $step,
      new HarbormasterBuildStepTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => pht('Step %d', $id),
      ));
  }

  private function buildPropertyList(
    HarbormasterBuildStep $step,
    PhabricatorCustomFieldList $field_list) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($step);

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

    $view->invokeWillRenderEvent();

    $description = $step->getDescription();
    if (strlen($description)) {
      $view->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent(
        new PHUIRemarkupView($viewer, $description));
    }

    return $view;
  }


  private function buildActionList(HarbormasterBuildStep $step) {
    $viewer = $this->getViewer();
    $id = $step->getID();

    $list = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($step);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $step,
      PhabricatorPolicyCapability::CAN_EDIT);

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Step'))
        ->setHref($this->getApplicationURI("step/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit)
        ->setIcon('fa-pencil'));

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete Step'))
        ->setHref($this->getApplicationURI("step/delete/{$id}/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setIcon('fa-times'));

    return $list;
  }


}
