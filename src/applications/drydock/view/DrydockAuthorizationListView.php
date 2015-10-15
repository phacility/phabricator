<?php

final class DrydockAuthorizationListView extends AphrontView {

  private $authorizations;
  private $noDataString;

  public function setAuthorizations(array $authorizations) {
    assert_instances_of($authorizations, 'DrydockAuthorization');
    $this->authorizations = $authorizations;
    return $this;
  }

  public function setNoDataString($string) {
    $this->noDataString = $string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
  }

  public function render() {
    $viewer = $this->getUser();

    $authorizations = $this->authorizations;

    $view = new PHUIObjectItemListView();

    $nodata = $this->getNoDataString();
    if ($nodata) {
      $view->setNoDataString($nodata);
    }

    $handles = $viewer->loadHandles(mpull($authorizations, 'getObjectPHID'));

    foreach ($authorizations as $authorization) {
      $id = $authorization->getID();
      $object_phid = $authorization->getObjectPHID();
      $handle = $handles[$object_phid];

      $item = id(new PHUIObjectItemView())
        ->setHref("/drydock/authorization/{$id}/")
        ->setObjectName(pht('Authorization %d', $id))
        ->setHeader($handle->getFullName());

      $item->addAttribute($handle->getTypeName());

      $object_state = $authorization->getObjectAuthorizationState();
      $item->addAttribute(
        DrydockAuthorization::getObjectStateName($object_state));

      $state = $authorization->getBlueprintAuthorizationState();
      $icon = DrydockAuthorization::getBlueprintStateIcon($state);
      $name = DrydockAuthorization::getBlueprintStateName($state);

      $item->setStatusIcon($icon, $name);

      $view->addItem($item);
    }

    return $view;
  }

}
