<?php

final class HeraldWebhookURITransaction
  extends HeraldWebhookTransactionType {

  const TRANSACTIONTYPE = 'uri';

  public function generateOldValue($object) {
    return $object->getWebhookURI();
  }

  public function applyInternalEffects($object, $value) {
    $object->setWebhookURI($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the URI for this webhook from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the URI for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $viewer = $this->getActor();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Webhooks must have a URI.'));
      return $errors;
    }

    $max_length = $object->getColumnMaximumByteLength('webhookURI');
    foreach ($xactions as $xaction) {
      $old_value = $this->generateOldValue($object);
      $new_value = $xaction->getNewValue();

      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Webhook URIs can be no longer than %s characters.',
            new PhutilNumber($max_length)),
          $xaction);
      }

      try {
        PhabricatorEnv::requireValidRemoteURIForFetch(
          $new_value,
          array(
            'http',
            'https',
          ));
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          $ex->getMessage(),
          $xaction);
      }
    }

    return $errors;
  }

}
