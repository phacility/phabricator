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

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $xaction_view,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
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
        ->setName(pht('Manually Execute Plan'))
        ->setHref($this->getApplicationURI("plan/execute/{$id}/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setIcon('arrow_right'));

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
