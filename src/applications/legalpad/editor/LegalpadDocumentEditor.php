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
    $types[] = LegalpadTransactionType::TYPE_SIGNATURE_TYPE;
    $types[] = LegalpadTransactionType::TYPE_PREAMBLE;

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
      case LegalpadTransactionType::TYPE_SIGNATURE_TYPE:
        return $object->getSignatureType();
      case LegalpadTransactionType::TYPE_PREAMBLE:
        return $object->getPreamble();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransactionType::TYPE_TITLE:
      case LegalpadTransactionType::TYPE_TEXT:
      case LegalpadTransactionType::TYPE_SIGNATURE_TYPE:
      case LegalpadTransactionType::TYPE_PREAMBLE:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransactionType::TYPE_TITLE:
        $object->setTitle($xaction->getNewValue());
        $body = $object->getDocumentBody();
        $body->setTitle($xaction->getNewValue());
        $this->setIsContribution(true);
        break;
      case LegalpadTransactionType::TYPE_TEXT:
        $body = $object->getDocumentBody();
        $body->setText($xaction->getNewValue());
        $this->setIsContribution(true);
        break;
      case LegalpadTransactionType::TYPE_SIGNATURE_TYPE:
        $object->setSignatureType($xaction->getNewValue());
        break;
      case LegalpadTransactionType::TYPE_PREAMBLE:
        $object->setPreamble($xaction->getNewValue());
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

      $actor = $this->getActor();
      $type = PhabricatorEdgeConfig::TYPE_CONTRIBUTED_TO_OBJECT;
      id(new PhabricatorEdgeEditor())
        ->addEdge($actor->getPHID(), $type, $object->getPHID())
        ->setActor($actor)
        ->save();

      $type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_CONTRIBUTOR;
      $contributors = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $object->getPHID(),
        $type);
      $object->setRecentContributorPHIDs(array_slice($contributors, 0, 3));
      $object->setContributorCount(count($contributors));

      $object->save();
    }

    return $xactions;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case LegalpadTransactionType::TYPE_TITLE:
      case LegalpadTransactionType::TYPE_TEXT:
      case LegalpadTransactionType::TYPE_SIGNATURE_TYPE:
      case LegalpadTransactionType::TYPE_PREAMBLE:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

/* -(  Sending Mail  )------------------------------------------------------- */

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new LegalpadReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $phid = $object->getPHID();
    $title = $object->getDocumentBody()->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("L{$id}: {$title}")
      ->addHeader('Thread-Topic', "L{$id}: {$phid}");
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getCreatorPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function shouldImplyCC(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransactionType::TYPE_TEXT:
      case LegalpadTransactionType::TYPE_TITLE:
      case LegalpadTransactionType::TYPE_PREAMBLE:
        return true;
    }

    return parent::shouldImplyCC($object, $xaction);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addTextSection(
      pht('DOCUMENT DETAIL'),
      PhabricatorEnv::getProductionURI('/legalpad/view/'.$object->getID().'/'));

    return $body;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.legalpad.subject-prefix');
  }


  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  protected function supportsSearch() {
    return false;
  }

}
