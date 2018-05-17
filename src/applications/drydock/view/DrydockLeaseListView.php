<?php

final class DrydockLeaseListView extends AphrontView {

  private $leases;

  public function setLeases(array $leases) {
    assert_instances_of($leases, 'DrydockLease');
    $this->leases = $leases;
    return $this;
  }

  public function render() {
    $leases = $this->leases;
    $viewer = $this->getUser();

    $view = new PHUIObjectItemListView();

    foreach ($leases as $lease) {
      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setHeader($lease->getLeaseName())
        ->setHref('/drydock/lease/'.$lease->getID().'/');

      $resource_phid = $lease->getResourcePHID();
      if ($resource_phid) {
        $item->addAttribute(
          $viewer->renderHandle($resource_phid));
      } else {
        $item->addAttribute(
          pht(
            'Resource: %s',
            $lease->getResourceType()));
      }

      $item->setEpoch($lease->getDateCreated());

      $icon = $lease->getStatusIcon();
      $color = $lease->getStatusColor();
      $label = $lease->getStatusDisplayName();

      $item->setStatusIcon("{$icon} {$color}", $label);

      $view->addItem($item);
    }

    return $view;
  }

}
