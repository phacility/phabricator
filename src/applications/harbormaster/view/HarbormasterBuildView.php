<?php

final class HarbormasterBuildView
  extends AphrontView {

  private $builds = array();

  public function setBuilds(array $builds) {
    assert_instances_of($builds, 'HarbormasterBuild');
    $this->builds = $builds;
    return $this;
  }

  public function getBuilds() {
    return $this->builds;
  }

  public function render() {
    return $this->newObjectList();
  }

  public function newObjectList() {
    $viewer = $this->getViewer();
    $builds = $this->getBuilds();

    $buildables = mpull($builds, 'getBuildable');
    $object_phids = mpull($buildables, 'getBuildablePHID');
    $initiator_phids = mpull($builds, 'getInitiatorPHID');
    $phids = array_mergev(array($initiator_phids, $object_phids));
    $phids = array_unique(array_filter($phids));

    $handles = $viewer->loadHandles($phids);

    $list = new PHUIObjectItemListView();
    foreach ($builds as $build) {
      $id = $build->getID();

      $buildable_object = $handles[$build->getBuildable()->getBuildablePHID()];

      $item = id(new PHUIObjectItemView())
        ->setViewer($viewer)
        ->setObject($build)
        ->setObjectName($build->getObjectName())
        ->setHeader($build->getName())
        ->setHref($build->getURI())
        ->setEpoch($build->getDateCreated())
        ->addAttribute($buildable_object->getName());

      $initiator_phid = $build->getInitiatorPHID();
      if ($initiator_phid) {
        $initiator = $handles[$initiator_phid];
        $item->addByline($initiator->renderLink());
      }

      $status = $build->getBuildStatus();

      $status_icon = HarbormasterBuildStatus::getBuildStatusIcon($status);
      $status_color = HarbormasterBuildStatus::getBuildStatusColor($status);
      $status_label = HarbormasterBuildStatus::getBuildStatusName($status);

      $item->setStatusIcon("{$status_icon} {$status_color}", $status_label);

      $list->addItem($item);
    }

    return $list;
  }

}
