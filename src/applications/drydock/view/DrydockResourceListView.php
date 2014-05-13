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
          $item->setBarColor('yellow');
          break;
        case DrydockResourceStatus::STATUS_OPEN:
          $item->setBarColor('green');
          break;
        case DrydockResourceStatus::STATUS_DESTROYED:
          $item->setBarColor('black');
          break;
        default:
          $item->setBarColor('red');
          break;
      }

      $view->addItem($item);
    }

    return $view;
  }

}
