<?php

final class HarbormasterBuildableViewController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $this->id;

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needBuildableHandles(true)
      ->needContainerObjects(true)
      ->executeOne();
    if (!$buildable) {
      return new Aphront404Response();
    }

    $builds = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array($buildable->getPHID()))
      ->needBuildPlans(true)
      ->execute();

    $build_list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($builds as $build) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Build %d', $build->getID()))
        ->setHeader($build->getName());
      $build_list->addItem($item);
    }

    $title = pht("Buildable %d", $id);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($buildable);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    $actions = $this->buildActionList($buildable);
    $this->buildPropertyLists($box, $buildable, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName("B{$id}"));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $build_list,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildActionList(HarbormasterBuildable $buildable) {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $id = $buildable->getID();

    $list = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($buildable)
      ->setObjectURI("/B{$id}");

    return $list;
  }

  private function buildPropertyLists(
    PHUIObjectBoxView $box,
    HarbormasterBuildable $buildable,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($buildable)
      ->setActionList($actions);
    $box->addPropertyList($properties);

    $properties->addProperty(
      pht('Buildable'),
      $buildable->getBuildableHandle()->renderLink());

  }

}
