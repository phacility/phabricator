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
      ->setPolicyObject($blueprint)
      ->setHeaderIcon('fa-map-o');

    if ($blueprint->getIsDisabled()) {
      $header->setStatus('fa-ban', 'red', pht('Disabled'));
    } else {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    }

    $curtain = $this->buildCurtain($blueprint);
    $properties = $this->buildPropertyListView($blueprint);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Blueprint %d', $blueprint->getID()));
    $crumbs->setBorder(true);

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

    $resources = $this->buildResourceBox($blueprint);
    $authorizations = $this->buildAuthorizationsBox($blueprint);

    $timeline = $this->buildTransactionTimeline(
      $blueprint,
      new DrydockBlueprintTransactionQuery());
    $timeline->setShouldTerminate(true);

    $log_query = id(new DrydockLogQuery())
      ->withBlueprintPHIDs(array($blueprint->getPHID()));

    $logs = $this->buildLogBox(
      $log_query,
      $this->getApplicationURI("blueprint/{$id}/logs/query/all/"));

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Properties'), $properties)
      ->setMainColumn(array(
        $resources,
        $authorizations,
        $logs,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));

  }

  private function buildCurtain(DrydockBlueprint $blueprint) {
    $viewer = $this->getViewer();
    $id = $blueprint->getID();

    $curtain = $this->newCurtainView($blueprint);
    $edit_uri = $this->getApplicationURI("blueprint/edit/{$id}/");

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $blueprint,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
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

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setHref($disable_uri)
        ->setName($disable_name)
        ->setIcon($disable_icon)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

  private function buildPropertyListView(
    DrydockBlueprint $blueprint) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Type'),
      $blueprint->getImplementation()->getBlueprintName());

    return $view;
  }

  private function buildResourceBox(DrydockBlueprint $blueprint) {
    $viewer = $this->getViewer();

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withBlueprintPHIDs(array($blueprint->getPHID()))
      ->withStatuses(
        array(
          DrydockResourceStatus::STATUS_PENDING,
          DrydockResourceStatus::STATUS_ACTIVE,
        ))
      ->setLimit(100)
      ->execute();

    $resource_list = id(new DrydockResourceListView())
      ->setUser($viewer)
      ->setResources($resources)
      ->render()
      ->setNoDataString(pht('This blueprint has no active resources.'));

    $id = $blueprint->getID();
    $resources_uri = "blueprint/{$id}/resources/query/all/";
    $resources_uri = $this->getApplicationURI($resources_uri);

    $resource_header = id(new PHUIHeaderView())
      ->setHeader(pht('Active Resources'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($resources_uri)
          ->setIcon('fa-search')
          ->setText(pht('View All')));

    return id(new PHUIObjectBoxView())
      ->setHeader($resource_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($resource_list);
  }

  private function buildAuthorizationsBox(DrydockBlueprint $blueprint) {
    $viewer = $this->getViewer();

    $limit = 25;

    // If there are pending authorizations against this blueprint, make sure
    // we show them first.

    $pending_authorizations = id(new DrydockAuthorizationQuery())
      ->setViewer($viewer)
      ->withBlueprintPHIDs(array($blueprint->getPHID()))
      ->withObjectStates(
        array(
          DrydockAuthorization::OBJECTAUTH_ACTIVE,
        ))
      ->withBlueprintStates(
        array(
          DrydockAuthorization::BLUEPRINTAUTH_REQUESTED,
        ))
      ->setLimit($limit)
      ->execute();

    $all_authorizations = id(new DrydockAuthorizationQuery())
      ->setViewer($viewer)
      ->withBlueprintPHIDs(array($blueprint->getPHID()))
      ->withObjectStates(
        array(
          DrydockAuthorization::OBJECTAUTH_ACTIVE,
        ))
      ->withBlueprintStates(
        array(
          DrydockAuthorization::BLUEPRINTAUTH_REQUESTED,
          DrydockAuthorization::BLUEPRINTAUTH_AUTHORIZED,
        ))
      ->setLimit($limit)
      ->execute();

    $authorizations =
      mpull($pending_authorizations, null, 'getPHID') +
      mpull($all_authorizations, null, 'getPHID');

    $authorization_list = id(new DrydockAuthorizationListView())
      ->setUser($viewer)
      ->setAuthorizations($authorizations)
      ->setNoDataString(
        pht('No objects have active authorizations to use this blueprint.'));

    $id = $blueprint->getID();
    $authorizations_uri = "blueprint/{$id}/authorizations/query/all/";
    $authorizations_uri = $this->getApplicationURI($authorizations_uri);

    $authorizations_header = id(new PHUIHeaderView())
      ->setHeader(pht('Active Authorizations'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($authorizations_uri)
          ->setIcon('fa-search')
          ->setText(pht('View All')));

    return id(new PHUIObjectBoxView())
      ->setHeader($authorizations_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($authorization_list);

  }


}
