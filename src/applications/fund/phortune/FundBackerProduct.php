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

    if (!$initiative) {
      return pht('Fund <Unknown Initiative>');
    } else {
      return pht(
        'Fund %s %s',
        $initiative->getMonogram(),
        $initiative->getName());
    }
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
      throw new Exception(pht('Unable to load %s!', 'FundBacker'));
    }

    // Load the actual backing user -- they may not be the curent viewer if this
    // product purchase is completing from a background worker or a merchant
    // action.

    $actor = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($backer->getBackerPHID()))
      ->executeOne();

    $xactions = array();
    $xactions[] = id(new FundInitiativeTransaction())
      ->setTransactionType(FundInitiativeTransaction::TYPE_BACKER)
      ->setMetadataValue(
        FundInitiativeTransaction::PROPERTY_AMOUNT,
        $backer->getAmountAsCurrency()->serializeForStorage())
      ->setNewValue($backer->getPHID());

    $editor = id(new FundInitiativeEditor())
      ->setActor($actor)
      ->setContentSource($this->getContentSource());

    $editor->applyTransactions($this->getInitiative(), $xactions);
  }

  public function didRefundProduct(
    PhortuneProduct $product,
    PhortunePurchase $purchase,
    PhortuneCurrency $amount) {
    $viewer = $this->getViewer();

    $backer = id(new FundBackerQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($purchase->getMetadataValue('backerPHID')))
      ->executeOne();
    if (!$backer) {
      throw new Exception(pht('Unable to load %s!', 'FundBacker'));
    }

    $xactions = array();
    $xactions[] = id(new FundInitiativeTransaction())
      ->setTransactionType(FundInitiativeTransaction::TYPE_REFUND)
      ->setMetadataValue(
        FundInitiativeTransaction::PROPERTY_AMOUNT,
        $amount->serializeForStorage())
      ->setMetadataValue(
        FundInitiativeTransaction::PROPERTY_BACKER,
        $backer->getBackerPHID())
      ->setNewValue($backer->getPHID());

    $editor = id(new FundInitiativeEditor())
      ->setActor($viewer)
      ->setContentSource($this->getContentSource());

    $editor->applyTransactions($this->getInitiative(), $xactions);
  }

}
