<?php

/**
 * @group pholio
 */
final class PholioMockEditor extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $types[] = PholioTransactionType::TYPE_NAME;
    $types[] = PholioTransactionType::TYPE_DESCRIPTION;
    $types[] = PholioTransactionType::TYPE_INLINE;
    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
        return $object->getName();
      case PholioTransactionType::TYPE_DESCRIPTION:
        return $object->getDescription();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
      case PholioTransactionType::TYPE_DESCRIPTION:
        return $xaction->getNewValue();
    }
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PholioTransactionType::TYPE_INLINE:
        return true;
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        if ($object->getOriginalName() === null) {
          $object->setOriginalName($xaction->getNewValue());
        }
        break;
      case PholioTransactionType::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case PholioTransactionType::TYPE_NAME:
      case PholioTransactionType::TYPE_DESCRIPTION:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function supportsMail() {
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PholioReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();
    $original_name = $object->getOriginalName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("M{$id}: {$name}")
      ->addHeader('Thread-Topic', "M{$id}: {$original_name}");
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);
    $body->addTextSection(
      pht('MOCK DETAIL'),
      PhabricatorEnv::getProductionURI('/M'.$object->getID()));

    return $body;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.pholio.subject-prefix');
  }

  protected function supportsFeed() {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function sortTransactions(array $xactions) {
    $head = array();
    $tail = array();

    // Move inline comments to the end, so the comments preceed them.
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == PholioTransactionType::TYPE_INLINE) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }


  protected function shouldImplyCC(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PholioTransactionType::TYPE_INLINE:
        return true;
    }

    return parent::shouldImplyCC($object, $xaction);
  }

}
