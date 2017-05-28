<?php

final class FundInitiativeRefundTransaction
  extends FundInitiativeTransactionType {

  const TRANSACTIONTYPE = 'fund:refund';

  public function generateOldValue($object) {
    return null;
  }

  public function applyInternalEffects($object, $value) {
    $amount = $this->getMetadataValue(
      FundInitiativeTransaction::PROPERTY_AMOUNT);
    $amount = PhortuneCurrency::newFromString($amount);
    $total = $object->getTotalAsCurrency()->subtract($amount);
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
    $amount = $this->getMetadataValue(
      FundInitiativeTransaction::PROPERTY_AMOUNT);
    $subx[] = id(new FundBackerTransaction())
      ->setTransactionType(FundBackerStatusTransaction::TRANSACTIONTYPE)
      ->setNewValue($amount);

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
    $backer_phid = $this->getMetadataValue(
      FundInitiativeTransaction::PROPERTY_BACKER);

    return pht(
      '%s refunded %s to %s.',
      $this->renderAuthor(),
      $amount->formatForDisplay(),
      $this->renderHandle($backer_phid));
  }

  public function getTitleForFeed() {
    $amount = $this->getMetadataValue(
      FundInitiativeTransaction::PROPERTY_AMOUNT);
    $amount = PhortuneCurrency::newFromString($amount);
    $backer_phid = $this->getMetadataValue(
      FundInitiativeTransaction::PROPERTY_BACKER);

    return pht(
      '%s refunded %s to %s for %s.',
      $this->renderAuthor(),
      $amount->formatForDisplay(),
      $this->renderHandle($backer_phid),
      $this->renderObject());
  }


}
