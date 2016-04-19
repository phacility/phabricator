<?php

final class PhabricatorSpacesViewController
  extends PhabricatorSpacesController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $space = id(new PhabricatorSpacesNamespaceQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$space) {
      return new Aphront404Response();
    }

    $curtain = $this->buildCurtain($space);
    $property_list = $this->buildPropertyListView($space);
    $title = array($space->getMonogram(), $space->getNamespaceName());

    $xactions = id(new PhabricatorSpacesNamespaceTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($space->getPHID()))
      ->execute();

    $timeline = $this->buildTransactionTimeline(
      $space,
      new PhabricatorSpacesNamespaceTransactionQuery());
    $timeline->setShouldTerminate(true);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($space->getNamespaceName())
      ->setPolicyObject($space)
      ->setHeaderIcon('fa-th-large');

    if ($space->getIsArchived()) {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    } else {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($property_list);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($space->getMonogram());
    $crumbs->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn(array(
          $box,
          $timeline,
        ))
      ->setCurtain($curtain);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function buildPropertyListView(PhabricatorSpacesNamespace $space) {
    $viewer = $this->getRequest()->getUser();

    $list = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $list->addProperty(
      pht('Default Space'),
      $space->getIsDefaultNamespace()
        ? pht('Yes')
        : pht('No'));

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $space);

    $list->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $description = $space->getDescription();
    if (strlen($description)) {
      $description = new PHUIRemarkupView($viewer, $description);
      $list->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $list->addTextContent($description);
    }

    return $list;
  }

  private function buildCurtain(PhabricatorSpacesNamespace $space) {
    $viewer = $this->getRequest()->getUser();

    $curtain = $this->newCurtainView($space);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $space,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Space'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('edit/'.$space->getID().'/'))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    $id = $space->getID();

    if ($space->getIsArchived()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Space'))
          ->setIcon('fa-check')
          ->setHref($this->getApplicationURI("activate/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Space'))
          ->setIcon('fa-ban')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    return $curtain;
  }

}
