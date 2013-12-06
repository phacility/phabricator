<?php

final class HarbormasterPlanViewController
  extends HarbormasterPlanController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $this->id;

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $xactions = id(new HarbormasterBuildPlanTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($plan->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($plan->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $title = pht("Plan %d", $id);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($plan);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    $actions = $this->buildActionList($plan);
    $this->buildPropertyLists($box, $plan, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht("Plan %d", $id)));

    $step_list = $this->buildStepList($plan);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $step_list,
        $xaction_view,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildStepList(HarbormasterBuildPlan $plan) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $list_id = celerity_generate_unique_node_id();

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withBuildPlanPHIDs(array($plan->getPHID()))
      ->execute();

    $can_edit = $this->hasApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $i = 1;
    $step_list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setID($list_id);
    Javelin::initBehavior(
      'harbormaster-reorder-steps',
      array(
        'listID' => $list_id,
        'orderURI' => '/harbormaster/plan/order/'.$plan->getID().'/',
      ));
    foreach ($steps as $step) {
      $implementation = null;
      try {
        $implementation = $step->getStepImplementation();
      } catch (Exception $ex) {
        // We can't initialize the implementation.  This might be because
        // it's been renamed or no longer exists.
        $item = id(new PHUIObjectItemView())
          ->setObjectName("Step ".$i++)
          ->setHeader(pht('Unknown Implementation'))
          ->setBarColor('red')
          ->addAttribute(pht(
            'This step has an invalid implementation (%s).',
            $step->getClassName()))
          ->addAction(
            id(new PHUIListItemView())
              ->setIcon('delete')
              ->addSigil('harbormaster-build-step-delete')
              ->setWorkflow(true)
              ->setRenderNameAsTooltip(true)
              ->setName(pht("Delete"))
              ->setHref(
                $this->getApplicationURI("step/delete/".$step->getID()."/")));
        $step_list->addItem($item);
        continue;
      }
      $item = id(new PHUIObjectItemView())
        ->setObjectName("Step ".$i++)
        ->setHeader($implementation->getName());

      if (!$implementation->validateSettings()) {
        $item
          ->setBarColor('red')
          ->addAttribute(pht('This step is not configured correctly.'));
      } else {
        $item->addAttribute($implementation->getDescription());
      }

      if ($can_edit) {
        $edit_uri = $this->getApplicationURI("step/edit/".$step->getID()."/");
        $item
          ->setHref($edit_uri)
          ->addAction(
            id(new PHUIListItemView())
              ->setIcon('delete')
              ->addSigil('harbormaster-build-step-delete')
              ->setWorkflow(true)
              ->setRenderNameAsTooltip(true)
              ->setName(pht("Delete"))
              ->setHref(
                $this->getApplicationURI("step/delete/".$step->getID()."/")));
        $item->setGrippable(true);
        $item->addSigil('build-step');
        $item->setMetadata(
          array(
            'stepID' => $step->getID(),
          ));
      }

      $step_list->addItem($item);
    }

    return $step_list;
  }

  private function buildActionList(HarbormasterBuildPlan $plan) {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $id = $plan->getID();

    $list = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($plan)
      ->setObjectURI($this->getApplicationURI("plan/{$id}/"));

    $can_edit = $this->hasApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Plan'))
        ->setHref($this->getApplicationURI("plan/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit)
        ->setIcon('edit'));

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Add Build Step'))
        ->setHref($this->getApplicationURI("step/add/{$id}/"))
        ->setWorkflow($can_edit)
        ->setDisabled(!$can_edit)
        ->setIcon('new'));

    return $list;
  }

  private function buildPropertyLists(
    PHUIObjectBoxView $box,
    HarbormasterBuildPlan $plan,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($plan)
      ->setActionList($actions);
    $box->addPropertyList($properties);

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($plan->getDateCreated(), $viewer));

  }

}
