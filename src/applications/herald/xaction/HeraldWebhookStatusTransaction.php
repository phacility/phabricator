<?php

final class HeraldWebhookStatusTransaction
  extends HeraldWebhookTransactionType {

  const TRANSACTIONTYPE = 'status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    return pht(
      '%s changed hook status from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $viewer = $this->getActor();

    $options = HeraldWebhook::getStatusDisplayNameMap();

    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if (!isset($options[$new_value])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Webhook status "%s" is not valid. Valid statuses are: %s.',
            $new_value,
            implode(', ', array_keys($options))),
          $xaction);
      }
    }

    return $errors;
  }

}
