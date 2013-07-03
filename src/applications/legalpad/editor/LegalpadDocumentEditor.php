<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentEditor
  extends PhabricatorApplicationTransactionEditor {

  private $isContribution = false;

  private function setIsContribution($is_contribution) {
    $this->isContribution = $is_contribution;
  }
  private function isContribution() {
    return $this->isContribution;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $types[] = LegalpadTransactionType::TYPE_TITLE;
    $types[] = LegalpadTransactionType::TYPE_TEXT;
    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransactionType::TYPE_TITLE:
        return $object->getDocumentBody()->getTitle();
      case LegalpadTransactionType::TYPE_TEXT:
        return $object->getDocumentBody()->getText();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransactionType::TYPE_TITLE:
      case LegalpadTransactionType::TYPE_TEXT:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransactionType::TYPE_TITLE:
        $body = $object->getDocumentBody();
        $body->setTitle($xaction->getNewValue());
        $this->setIsContribution(true);
        break;
      case LegalpadTransactionType::TYPE_TEXT:
        $body = $object->getDocumentBody();
        $body->setText($xaction->getNewValue());
        $this->setIsContribution(true);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if ($this->isContribution()) {
      $object->setVersions($object->getVersions() + 1);
      $body = $object->getDocumentBody();
      $body->setVersion($object->getVersions());
      $body->setDocumentPHID($object->getPHID());
      $body->save();

      $object->setDocumentBodyPHID($body->getPHID());
      $object->save();

      $actor = $this->getActor();
      $type = PhabricatorEdgeConfig::TYPE_CONTRIBUTED_TO_OBJECT;
      id(new PhabricatorEdgeEditor())
        ->addEdge($actor->getPHID(), $type, $object->getPHID())
        ->setActor($actor)
        ->save();
    }
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case LegalpadTransactionType::TYPE_TITLE:
      case LegalpadTransactionType::TYPE_TEXT:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function supportsMail() {
    return false;
  }

  protected function supportsFeed() {
    return false;
  }

  protected function supportsSearch() {
    return false;
  }

}
