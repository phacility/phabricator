<?php

final class LegalpadDocumentEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorLegalpadApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Legalpad Documents');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this document.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $is_contribution = false;

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case LegalpadDocumentTitleTransaction::TRANSACTIONTYPE:
        case LegalpadDocumentTextTransaction::TRANSACTIONTYPE:
          $is_contribution = true;
          break;
      }
    }

    if ($is_contribution) {
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

  protected function validateAllTransactions(PhabricatorLiskDAO $object,
    array $xactions) {
    $errors = array();

    $is_required = (bool)$object->getRequireSignature();
    $document_type = $object->getSignatureType();
    $individual = LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL;

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case LegalpadDocumentRequireSignatureTransaction::TRANSACTIONTYPE:
          $is_required = (bool)$xaction->getNewValue();
          break;
        case LegalpadDocumentSignatureTypeTransaction::TRANSACTIONTYPE:
          $document_type = $xaction->getNewValue();
          break;
      }
    }

    if ($is_required && ($document_type != $individual)) {
      $errors[] = new PhabricatorApplicationTransactionValidationError(
        LegalpadDocumentRequireSignatureTransaction::TRANSACTIONTYPE,
        pht('Invalid'),
        pht('Only documents with signature type "individual" may '.
            'require signing to use Phabricator.'),
        null);
    }

    return $errors;
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
      case LegalpadDocumentTextTransaction::TRANSACTIONTYPE:
      case LegalpadDocumentTitleTransaction::TRANSACTIONTYPE:
      case LegalpadDocumentPreambleTransaction::TRANSACTIONTYPE:
      case LegalpadDocumentRequireSignatureTransaction::TRANSACTIONTYPE:
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
