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
      $id = $resource->getID();

      $item = id(new PHUIObjectItemView())
        ->setHref("/drydock/resource/{$id}/")
        ->setObjectName(pht('Resource %d', $id))
        ->setHeader($resource->getResourceName());

      $icon = $resource->getStatusIcon();
      $color = $resource->getStatusColor();
      $label = $resource->getStatusDisplayName();

      $item->setStatusIcon("{$icon} {$color}", $label);

      $view->addItem($item);
    }

    return $view;
  }

}
