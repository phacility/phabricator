<?php

final class LegalpadDocumentEditor
  extends PhabricatorApplicationTransactionEditor {

  private $isContribution = false;

  public function getEditorApplicationClass() {
    return 'PhabricatorLegalpadApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Legalpad Documents');
  }

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

    $types[] = LegalpadTransaction::TYPE_TITLE;
    $types[] = LegalpadTransaction::TYPE_TEXT;
    $types[] = LegalpadTransaction::TYPE_SIGNATURE_TYPE;
    $types[] = LegalpadTransaction::TYPE_PREAMBLE;
    $types[] = LegalpadTransaction::TYPE_REQUIRE_SIGNATURE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransaction::TYPE_TITLE:
        return $object->getDocumentBody()->getTitle();
      case LegalpadTransaction::TYPE_TEXT:
        return $object->getDocumentBody()->getText();
      case LegalpadTransaction::TYPE_SIGNATURE_TYPE:
        return $object->getSignatureType();
      case LegalpadTransaction::TYPE_PREAMBLE:
        return $object->getPreamble();
      case LegalpadTransaction::TYPE_REQUIRE_SIGNATURE:
        return (bool)$object->getRequireSignature();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransaction::TYPE_TITLE:
      case LegalpadTransaction::TYPE_TEXT:
      case LegalpadTransaction::TYPE_SIGNATURE_TYPE:
      case LegalpadTransaction::TYPE_PREAMBLE:
        return $xaction->getNewValue();
      case LegalpadTransaction::TYPE_REQUIRE_SIGNATURE:
        return (bool)$xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransaction::TYPE_TITLE:
        $object->setTitle($xaction->getNewValue());
        $body = $object->getDocumentBody();
        $body->setTitle($xaction->getNewValue());
        $this->setIsContribution(true);
        break;
      case LegalpadTransaction::TYPE_TEXT:
        $body = $object->getDocumentBody();
        $body->setText($xaction->getNewValue());
        $this->setIsContribution(true);
        break;
      case LegalpadTransaction::TYPE_SIGNATURE_TYPE:
        $object->setSignatureType($xaction->getNewValue());
        break;
      case LegalpadTransaction::TYPE_PREAMBLE:
        $object->setPreamble($xaction->getNewValue());
        break;
      case LegalpadTransaction::TYPE_REQUIRE_SIGNATURE:
        $object->setRequireSignature((int)$xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case LegalpadTransaction::TYPE_REQUIRE_SIGNATURE:
        if ($xaction->getNewValue()) {
          $session = new PhabricatorAuthSession();
          queryfx(
            $session->establishConnection('w'),
            'UPDATE %T SET signedLegalpadDocuments = 0',
            $session->getTableName());
        }
        break;
    }
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
      $type = PhabricatorContributedToObjectEdgeType::EDGECONST;
      id(new PhabricatorEdgeEditor())
        ->addEdge($actor->getPHID(), $type, $object->getPHID())
        ->save();

      $type = PhabricatorObjectHasContributorEdgeType::EDGECONST;
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
      case LegalpadTransaction::TYPE_TITLE:
      case LegalpadTransaction::TYPE_TEXT:
      case LegalpadTransaction::TYPE_SIGNATURE_TYPE:
      case LegalpadTransaction::TYPE_PREAMBLE:
      case LegalpadTransaction::TYPE_REQUIRE_SIGNATURE:
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
      case LegalpadTransaction::TYPE_TEXT:
      case LegalpadTransaction::TYPE_TITLE:
      case LegalpadTransaction::TYPE_PREAMBLE:
      case LegalpadTransaction::TYPE_REQUIRE_SIGNATURE:
        return true;
    }

    return parent::shouldImplyCC($object, $xaction);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addLinkSection(
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
