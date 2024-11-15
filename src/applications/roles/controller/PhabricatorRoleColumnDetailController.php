<?php

final class PhabricatorRoleColumnDetailController
  extends PhabricatorRoleBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $role_id = $request->getURIData('roleID');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->withIDs(array($role_id))
      ->needImages(true)
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }
    $this->setRole($role);

    $role_id = $role->getID();

    $column = id(new PhabricatorRoleColumnQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
        ->executeOne();
    if (!$column) {
      return new Aphront404Response();
    }

    $timeline = $this->buildTransactionTimeline(
      $column,
      new PhabricatorRoleColumnTransactionQuery());
    $timeline->setShouldTerminate(true);

    $title = $column->getDisplayName();

    $header = $this->buildHeaderView($column);
    $properties = $this->buildPropertyView($column);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Workboard'), $role->getWorkboardURI());
    $crumbs->addTextCrumb(pht('Column: %s', $title));
    $crumbs->setBorder(true);

    $nav = $this->newNavigation(
      $role,
      PhabricatorRole::ITEM_WORKBOARD);
    require_celerity_resource('project-view-css');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setMainColumn(array(
        $properties,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorRoleColumn $column) {
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Column: %s', $column->getDisplayName()))
      ->setUser($viewer);

    if ($column->isHidden()) {
      $header->setStatus('fa-ban', 'dark', pht('Hidden'));
    }

    return $header;
  }

  private function buildPropertyView(
    PhabricatorRoleColumn $column) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($column);

    $limit = $column->getPointLimit();
    if ($limit === null) {
      $limit_text = pht('No Limit');
    } else {
      $limit_text = $limit;
    }
    $properties->addProperty(pht('Point Limit'), $limit_text);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);

    return $box;
  }

}
