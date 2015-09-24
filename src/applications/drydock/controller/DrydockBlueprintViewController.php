<?php

final class DrydockBlueprintViewController extends DrydockBlueprintController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $blueprint = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$blueprint) {
      return new Aphront404Response();
    }

    $title = $blueprint->getBlueprintName();

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($blueprint);

    if ($blueprint->getIsDisabled()) {
      $header->setStatus('fa-ban', 'red', pht('Disabled'));
    } else {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    }

    $actions = $this->buildActionListView($blueprint);
    $properties = $this->buildPropertyListView($blueprint, $actions);

    $blueprint_uri = 'blueprint/'.$blueprint->getID().'/';
    $blueprint_uri = $this->getApplicationURI($blueprint_uri);

    $resources = id(new DrydockResourceQuery())
      ->withBlueprintPHIDs(array($blueprint->getPHID()))
      ->setViewer($viewer)
      ->execute();

    $resource_list = id(new DrydockResourceListView())
      ->setUser($viewer)
      ->setResources($resources)
      ->render();
    $resource_list->setNoDataString(pht('This blueprint has no resources.'));

    $pager = new PHUIPagerView();
    $pager->setURI(new PhutilURI($blueprint_uri), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Blueprint %d', $blueprint->getID()));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $field_list = PhabricatorCustomField::getObjectFields(
      $blueprint,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($blueprint);

    $field_list->appendFieldsToPropertyList(
      $blueprint,
      $viewer,
      $properties);

    $resource_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Resources'))
      ->setObjectList($resource_list);

    $timeline = $this->buildTransactionTimeline(
      $blueprint,
      new DrydockBlueprintTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $resource_box,
        $timeline,
      ),
      array(
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockBlueprint $blueprint) {
    $viewer = $this->getViewer();
    $id = $blueprint->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($blueprint);

    $edit_uri = $this->getApplicationURI("blueprint/edit/{$id}/");

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $blueprint,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setHref($edit_uri)
        ->setName(pht('Edit Blueprint'))
        ->setIcon('fa-pencil')
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    if (!$blueprint->getIsDisabled()) {
      $disable_name = pht('Disable Blueprint');
      $disable_icon = 'fa-ban';
      $disable_uri = $this->getApplicationURI("blueprint/{$id}/disable/");
    } else {
      $disable_name = pht('Enable Blueprint');
      $disable_icon = 'fa-check';
      $disable_uri = $this->getApplicationURI("blueprint/{$id}/enable/");
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setHref($disable_uri)
        ->setName($disable_name)
        ->setIcon($disable_icon)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    return $view;
  }

  private function buildPropertyListView(
    DrydockBlueprint $blueprint,
    PhabricatorActionListView $actions) {

    $view = new PHUIPropertyListView();
    $view->setActionList($actions);

    $view->addProperty(
      pht('Type'),
      $blueprint->getImplementation()->getBlueprintName());

    return $view;
  }

}
