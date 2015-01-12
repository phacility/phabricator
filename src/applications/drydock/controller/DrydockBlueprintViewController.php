<?php

final class DrydockBlueprintViewController extends DrydockBlueprintController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $blueprint = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$blueprint) {
      return new Aphront404Response();
    }

    $title = $blueprint->getBlueprintName();

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($blueprint);

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

    $pager = new AphrontPagerView();
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

    $timeline = $this->buildTransactionTimeline(
      $blueprint,
      new DrydockBlueprintTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $resource_list,
        $timeline,
      ),
      array(
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockBlueprint $blueprint) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($blueprint);

    $uri = '/blueprint/edit/'.$blueprint->getID().'/';
    $uri = $this->getApplicationURI($uri);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $blueprint,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setHref($uri)
        ->setName(pht('Edit Blueprint'))
        ->setIcon('fa-pencil')
        ->setWorkflow(!$can_edit)
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
