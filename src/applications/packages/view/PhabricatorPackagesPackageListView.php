<?php

final class PhabricatorPackagesPackageListView
  extends PhabricatorPackagesView {

  private $packages;

  public function setPackages(array $packages) {
    assert_instances_of($packages, 'PhabricatorPackagesPackage');
    $this->packages = $packages;
    return $this;
  }

  public function getPackages() {
    return $this->packages;
  }

  public function render() {
    return $this->newListView();
  }

  public function newListView() {
    $viewer = $this->getViewer();
    $packages = $this->getPackages();

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);

    foreach ($packages as $package) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($package->getFullKey())
        ->setHeader($package->getName())
        ->setHref($package->getURI());

      $list->addItem($item);
    }

    return $list;
  }

}
