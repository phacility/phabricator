<?php

final class PhortuneCartEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phortune Carts');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhortuneCartTransaction::TYPE_CREATED;
    $types[] = PhortuneCartTransaction::TYPE_PURCHASED;
    $types[] = PhortuneCartTransaction::TYPE_HOLD;
    $types[] = PhortuneCartTransaction::TYPE_REVIEW;
    $types[] = PhortuneCartTransaction::TYPE_CANCEL;
    $types[] = PhortuneCartTransaction::TYPE_REFUND;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneCartTransaction::TYPE_CREATED:
      case PhortuneCartTransaction::TYPE_PURCHASED:
      case PhortuneCartTransaction::TYPE_HOLD:
      case PhortuneCartTransaction::TYPE_REVIEW:
      case PhortuneCartTransaction::TYPE_CANCEL:
      case PhortuneCartTransaction::TYPE_REFUND:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneCartTransaction::TYPE_CREATED:
      case PhortuneCartTransaction::TYPE_PURCHASED:
      case PhortuneCartTransaction::TYPE_HOLD:
      case PhortuneCartTransaction::TYPE_REVIEW:
      case PhortuneCartTransaction::TYPE_CANCEL:
      case PhortuneCartTransaction::TYPE_REFUND:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneCartTransaction::TYPE_CREATED:
      case PhortuneCartTransaction::TYPE_PURCHASED:
      case PhortuneCartTransaction::TYPE_HOLD:
      case PhortuneCartTransaction::TYPE_REVIEW:
      case PhortuneCartTransaction::TYPE_CANCEL:
      case PhortuneCartTransaction::TYPE_REFUND:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneCartTransaction::TYPE_CREATED:
      case PhortuneCartTransaction::TYPE_PURCHASED:
      case PhortuneCartTransaction::TYPE_HOLD:
      case PhortuneCartTransaction::TYPE_REVIEW:
      case PhortuneCartTransaction::TYPE_CANCEL:
      case PhortuneCartTransaction::TYPE_REFUND:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject(pht('Order %d: %s', $id, $name))
      ->addHeader('Thread-Topic', pht('Order %s', $id));
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $items = array();
    foreach ($object->getPurchases() as $purchase) {
      $name = $purchase->getFullDisplayName();
      $price = $purchase->getTotalPriceAsCurrency()->formatForDisplay();

      $items[] = "{$name} {$price}";
    }

    $body->addTextSection(pht('ORDER CONTENTS'), implode("\n", $items));

    $body->addLinkSection(
      pht('ORDER DETAIL'),
      PhabricatorEnv::getProductionURI('/phortune/cart/'.$object->getID().'/'));

    return $body;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();

    // Relaod the cart to pull merchant and account information, in case we
    // just created the object.
    $cart = id(new PhortuneCartQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs(array($object->getPHID()))
      ->executeOne();

    foreach ($cart->getAccount()->getMemberPHIDs() as $account_member) {
      $phids[] = $account_member;
    }

    foreach ($cart->getMerchant()->getMemberPHIDs() as $merchant_member) {
      $phids[] = $merchant_member;
    }

    return $phids;
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return array();
  }

  protected function getMailSubjectPrefix() {
    return 'Order';
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhortuneCartReplyHandler())
      ->setMailReceiver($object);
  }

}
