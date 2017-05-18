<?php

final class FundInitiativeBackerTransaction
  extends FundInitiativeTransactionType {

  const TRANSACTIONTYPE = 'fund:backer';

  public function generateOldValue($object) {
    return null;
  }

  public function applyInternalEffects($object, $value) {
    $amount = $this->getMetadataValue(
      FundInitiativeTransaction::PROPERTY_AMOUNT);
    $amount = PhortuneCurrency::newFromString($amount);
    $total = $object->getTotalAsCurrency()->add($amount);
    $object->setTotalAsCurrency($total);
  }

  public function applyExternalEffects($object, $value) {
    $backer = id(new FundBackerQuery())
      ->setViewer($this->getActor())
      ->withPHIDs(array($value))
      ->executeOne();
    if (!$backer) {
      throw new Exception(pht('Unable to load %s!', 'FundBacker'));
    }

    $subx = array();
    $subx[] = id(new FundBackerTransaction())
      ->setTransactionType(FundBackerStatusTransaction::TRANSACTIONTYPE)
      ->setNewValue(FundBacker::STATUS_PURCHASED);

    $content_source = $this->getEditor()->getContentSource();

    $editor = id(new FundBackerEditor())
      ->setActor($this->getActor())
      ->setContentSource($content_source)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true);

    $editor->applyTransactions($backer, $subx);
  }

  public function getTitle() {
    $amount = $this->getMetadataValue(
      FundInitiativeTransaction::PROPERTY_AMOUNT);
    $amount = PhortuneCurrency::newFromString($amount);
    return pht(
      '%s backed this initiative with %s.',
      $this->renderAuthor(),
      $amount->formatForDisplay());
  }

  public function getTitleForFeed() {
    $amount = $this->getMetadataValue(
      FundInitiativeTransaction::PROPERTY_AMOUNT);
    $amount = PhortuneCurrency::newFromString($amount);
    return pht(
      '%s backed %s with %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $amount->formatForDisplay());
  }

  public function getIcon() {
    return 'fa-heart';
  }

  public function getColor() {
    return 'red';
  }


}
