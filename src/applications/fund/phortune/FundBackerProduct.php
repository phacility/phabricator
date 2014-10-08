<?php

final class FundBackerProduct extends PhortuneProductImplementation {

  private $initiativePHID;
  private $initiative;
  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function getRef() {
    return $this->getInitiativePHID();
  }

  public function getName(PhortuneProduct $product) {
    $initiative = $this->getInitiative();
    return pht(
      'Fund %s %s',
      $initiative->getMonogram(),
      $initiative->getName());
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
        ->setViewer($viewer)
        ->setInitiativePHID($ref);

      $initiative = idx($initiatives, $ref);
      if ($initiative) {
        $object->setInitiative($initiative);
      }

      $objects[] = $object;
    }

    return $objects;
  }

  public function didPurchaseProduct(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    $viewer = $this->getViewer();

    $backer = id(new FundBackerQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($purchase->getMetadataValue('backerPHID')))
      ->executeOne();
    if (!$backer) {
      throw new Exception(pht('Unable to load FundBacker!'));
    }

    $xactions = array();
    $xactions[] = id(new FundBackerTransaction())
      ->setTransactionType(FundBackerTransaction::TYPE_STATUS)
      ->setNewValue(FundBacker::STATUS_PURCHASED);

    $editor = id(new FundBackerEditor())
      ->setActor($viewer)
      ->setContentSource($this->getContentSource());

    $editor->applyTransactions($backer, $xactions);


    $xactions = array();
    $xactions[] = id(new FundInitiativeTransaction())
      ->setTransactionType(FundInitiativeTransaction::TYPE_BACKER)
      ->setNewValue($backer->getPHID());

    $editor = id(new FundInitiativeEditor())
      ->setActor($viewer)
      ->setContentSource($this->getContentSource());

    $editor->applyTransactions($this->getInitiative(), $xactions);

    return;
  }

}
