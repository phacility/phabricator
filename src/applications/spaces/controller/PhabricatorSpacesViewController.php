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

    $action_list = $this->buildActionListView($space);
    $property_list = $this->buildPropertyListView($space);
    $property_list->setActionList($action_list);

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
      ->setPolicyObject($space);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($property_list);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($space->getMonogram());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => array($space->getMonogram(), $space->getNamespaceName()),
      ));
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

    return $list;
  }

  private function buildActionListView(PhabricatorSpacesNamespace $space) {
    $viewer = $this->getRequest()->getUser();

    $list = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI('/'.$space->getMonogram());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $space,
      PhabricatorPolicyCapability::CAN_EDIT);

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Space'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('edit/'.$space->getID().'/'))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $list;
  }

}
