<?php

final class NuanceItemEditController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $item = id(new NuanceItemQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$item) {
      return new Aphront404Response();
    }

    $title = pht('Item %d', $item->getID());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->addTextCrumb(pht('Edit'));

    $properties = $this->buildPropertyView($item);
    $actions = $this->buildActionView($item);
    $properties->setActionList($actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $item,
      new NuanceItemTransactionQuery());

    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPropertyView(NuanceItem $item) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($item);

    $properties->addProperty(
      pht('Date Created'),
      phabricator_datetime($item->getDateCreated(), $viewer));

    $properties->addProperty(
      pht('Requestor'),
      $viewer->renderHandle($item->getRequestorPHID()));

    $properties->addProperty(
      pht('Source'),
      $viewer->renderHandle($item->getSourcePHID()));

    $properties->addProperty(
      pht('Queue'),
      $viewer->renderHandle($item->getQueuePHID()));

    $source = $item->getSource();
    $definition = $source->requireDefinition();

    $definition->renderItemEditProperties(
      $viewer,
      $item,
      $properties);

    return $properties;
  }

  private function buildActionView(NuanceItem $item) {
    $viewer = $this->getViewer();
    $id = $item->getID();

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Item'))
        ->setIcon('fa-eye')
        ->setHref($this->getApplicationURI("item/view/{$id}/")));

    return $actions;
  }


}
