<?php

final class PhabricatorPhurlURLLongURLTransaction
  extends PhabricatorPhurlURLTransactionType {

  const TRANSACTIONTYPE = 'phurl.longurl';

  public function generateOldValue($object) {
    return $object->getLongURL();
  }

  public function applyInternalEffects($object, $value) {
    $object->setLongURL($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the destination URL from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the destination URL %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getLongURL(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('URL path is required'));
    }

    foreach ($xactions as $xaction) {
      if ($xaction->getOldValue() != $xaction->getNewValue()) {
        $protocols = PhabricatorEnv::getEnvConfig('uri.allowed-protocols');
        $uri = new PhutilURI($xaction->getNewValue());
        if (!isset($protocols[$uri->getProtocol()])) {
          $errors[] = $this->newRequiredError(
            pht('The protocol of the URL is invalid.'));
        }
      }
    }

    return $errors;
  }

  public function getIcon() {
    return 'fa-external-link-square';
  }

}
