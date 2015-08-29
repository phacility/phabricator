<?php

final class NuanceItemViewController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $item = id(new NuanceItemQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$item) {
      return new Aphront404Response();
    }

    $title = pht('Item %d', $item->getID());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    $properties = $this->buildPropertyView($item);
    $actions = $this->buildActionView($item);
    $properties->setActionList($actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
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

    $source = $item->getSource();
    $definition = $source->requireDefinition();

    $definition->renderItemViewProperties(
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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $item,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Item'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("item/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $actions;
  }


}
