<?php

final class FundBackerProduct extends PhortuneProductImplementation {

  private $initiativePHID;
  private $initiative;

  public function getRef() {
    return $this->getInitiativePHID();
  }

  public function getName(PhortuneProduct $product) {
    return pht('Back Initiative %s', $this->initiativePHID);
  }

  public function getPriceAsCurrency(PhortuneProduct $product) {
    return PhortuneCurrency::newEmptyCurrency();
  }

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

  public function loadImplementationsForRefs(
    PhabricatorUser $viewer,
    array $refs) {

    $initiatives = id(new FundInitiativeQuery())
      ->setViewer($viewer)
      ->withPHIDs($refs)
      ->execute();
    $initiatives = mpull($initiatives, null, 'getPHID');

    $objects = array();
    foreach ($refs as $ref) {
      $object = id(new FundBackerProduct())
        ->setInitiativePHID($ref);

      $initiative = idx($initiatives, $ref);
      if ($initiative) {
        $object->setInitiative($initiative);
      }

      $objects[] = $object;
    }

    return $objects;
  }

}
