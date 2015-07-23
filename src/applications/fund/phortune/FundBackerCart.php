<?php

final class FundBackerCart extends PhortuneCartImplementation {

  private $initiativePHID;
  private $initiative;

  public function setInitiativePHID($initiative_phid) {
    $this->initiativePHID = $initiative_phid;
    return $this;
  }

  public function getInitiativePHID() {
    return $this->initiativePHID;
  }

  public function setInitiative(FundInitiative $initiative) {
    $this->initiative = $initiative;
    return $this;
  }

  public function getInitiative() {
    return $this->initiative;
  }

  public function getName(PhortuneCart $cart) {
    return pht('Fund Initiative');
  }

  public function willCreateCart(
    PhabricatorUser $viewer,
    PhortuneCart $cart) {

    $initiative = $this->getInitiative();
    if (!$initiative) {
      throw new PhutilInvalidStateException('setInitiative');
    }

    $cart->setMetadataValue('initiativePHID', $initiative->getPHID());
  }

  public function loadImplementationsForCarts(
    PhabricatorUser $viewer,
    array $carts) {

    $phids = array();
    foreach ($carts as $cart) {
      $phids[] = $cart->getMetadataValue('initiativePHID');
    }

    $initiatives = id(new FundInitiativeQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
    $initiatives = mpull($initiatives, null, 'getPHID');

    $objects = array();
    foreach ($carts as $key => $cart) {
      $initiative_phid = $cart->getMetadataValue('initiativePHID');

      $object = id(new FundBackerCart())
        ->setInitiativePHID($initiative_phid);

      $initiative = idx($initiatives, $initiative_phid);
      if ($initiative) {
        $object->setInitiative($initiative);
      }

      $objects[$key] = $object;
    }

    return $objects;
  }

  public function getCancelURI(PhortuneCart $cart) {
    return '/'.$this->getInitiative()->getMonogram();
  }

  public function getDoneURI(PhortuneCart $cart) {
    return '/'.$this->getInitiative()->getMonogram();
  }

  public function getDoneActionName(PhortuneCart $cart) {
    return pht('Return to Initiative');
  }

}
