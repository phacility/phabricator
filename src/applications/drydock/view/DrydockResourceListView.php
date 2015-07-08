<?php

final class DrydockResourceListView extends AphrontView {

  private $resources;

  public function setResources(array $resources) {
    assert_instances_of($resources, 'DrydockResource');
    $this->resources = $resources;
    return $this;
  }

  public function render() {
    $resources = $this->resources;
    $viewer = $this->getUser();

    $view = new PHUIObjectItemListView();
    foreach ($resources as $resource) {
      $name = pht('Resource %d', $resource->getID()).': '.$resource->getName();

      $item = id(new PHUIObjectItemView())
        ->setHref('/drydock/resource/'.$resource->getID().'/')
        ->setHeader($name);

      $status = DrydockResourceStatus::getNameForStatus($resource->getStatus());
      $item->addAttribute($status);

      switch ($resource->getStatus()) {
        case DrydockResourceStatus::STATUS_PENDING:
          $item->setStatusIcon('fa-dot-circle-o yellow');
          break;
        case DrydockResourceStatus::STATUS_OPEN:
          $item->setStatusIcon('fa-dot-circle-o green');
          break;
        case DrydockResourceStatus::STATUS_DESTROYED:
          $item->setStatusIcon('fa-times-circle-o black');
          break;
        default:
          $item->setStatusIcon('fa-dot-circle-o red');
          break;
      }

      $view->addItem($item);
    }

    return $view;
  }

}
