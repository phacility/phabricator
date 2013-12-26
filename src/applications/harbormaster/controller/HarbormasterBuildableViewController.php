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
      ->needContainerHandles(true)
      ->executeOne();
    if (!$buildable) {
      return new Aphront404Response();
    }

    $builds = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array($buildable->getPHID()))
      ->execute();

    $build_list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($builds as $build) {
      $view_uri = $this->getApplicationURI('/build/'.$build->getID().'/');
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Build %d', $build->getID()))
        ->setHeader($build->getName())
        ->setHref($view_uri);
      if ($build->getCancelRequested()) {
        $item->setBarColor('black');
        $item->addAttribute(pht('Cancelling'));
      } else {
        switch ($build->getBuildStatus()) {
          case HarbormasterBuild::STATUS_INACTIVE:
            $item->setBarColor('grey');
            $item->addAttribute(pht('Inactive'));
            break;
          case HarbormasterBuild::STATUS_PENDING:
            $item->setBarColor('blue');
            $item->addAttribute(pht('Pending'));
            break;
          case HarbormasterBuild::STATUS_WAITING:
            $item->setBarColor('violet');
            $item->addAttribute(pht('Waiting'));
            break;
          case HarbormasterBuild::STATUS_BUILDING:
            $item->setBarColor('yellow');
            $item->addAttribute(pht('Building'));
            break;
          case HarbormasterBuild::STATUS_PASSED:
            $item->setBarColor('green');
            $item->addAttribute(pht('Passed'));
            break;
          case HarbormasterBuild::STATUS_FAILED:
            $item->setBarColor('red');
            $item->addAttribute(pht('Failed'));
            break;
          case HarbormasterBuild::STATUS_ERROR:
            $item->setBarColor('red');
            $item->addAttribute(pht('Unexpected Error'));
            break;
          case HarbormasterBuild::STATUS_CANCELLED:
            $item->setBarColor('black');
            $item->addAttribute(pht('Cancelled'));
            break;
        }
      }
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
    $crumbs->addTextCrumb("B{$id}");

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

    if ($buildable->getContainerHandle() !== null) {
      $properties->addProperty(
        pht('Container'),
        $buildable->getContainerHandle()->renderLink());
    }

    $properties->addProperty(
      pht('Origin'),
      $buildable->getIsManualBuildable()
        ? pht('Manual Buildable')
        : pht('Automatic Buildable'));

  }

}
