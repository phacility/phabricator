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
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    $old_status = HeraldWebhook::getDisplayNameForStatus($old_value);
    $new_status = HeraldWebhook::getDisplayNameForStatus($new_value);

    return pht(
      '%s changed hook status from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old_status),
      $this->renderValue($new_status));
  }

  public function getTitleForFeed() {
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    $old_status = HeraldWebhook::getDisplayNameForStatus($old_value);
    $new_status = HeraldWebhook::getDisplayNameForStatus($new_value);

    return pht(
      '%s changed %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderValue($old_status),
      $this->renderValue($new_status));
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
