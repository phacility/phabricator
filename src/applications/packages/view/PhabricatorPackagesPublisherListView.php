<?php

final class PhabricatorPackagesPublisherListView
  extends PhabricatorPackagesView {

  private $publishers;

  public function setPublishers(array $publishers) {
    assert_instances_of($publishers, 'PhabricatorPackagesPublisher');
    $this->publishers = $publishers;
    return $this;
  }

  public function getPublishers() {
    return $this->publishers;
  }

  public function render() {
    return $this->newListView();
  }

  public function newListView() {
    $viewer = $this->getViewer();
    $publishers = $this->getPublishers();

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);

    foreach ($publishers as $publisher) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($publisher->getPublisherKey())
        ->setHeader($publisher->getName())
        ->setHref($publisher->getURI());

      $list->addItem($item);
    }

    return $list;
  }

}
