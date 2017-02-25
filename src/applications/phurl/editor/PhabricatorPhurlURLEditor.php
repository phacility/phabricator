<?php

final class PhabricatorPhurlURLEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhurlApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phurl');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this URL.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function supportsSearch() {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorPhurlURLTransaction::MAILTAG_DETAILS =>
        pht(
          "A URL's details change."),
    );
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Phurl]');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    $phids[] = $this->getActingAsPHID();

    return $phids;
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("U{$id}: {$name}")
      ->addHeader('Thread-Topic', "U{$id}: ".$object->getName());
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $description = $object->getDescription();
    $body = parent::buildMailBody($object, $xactions);

    if (strlen($description)) {
      $body->addRemarkupSection(
        pht('URL DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('URL DETAIL'),
      PhabricatorEnv::getProductionURI('/U'.$object->getID()));


    return $body;
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();
    $errors[] = new PhabricatorApplicationTransactionValidationError(
      PhabricatorPhurlURLAliasTransaction::TRANSACTIONTYPE,
      pht('Duplicate'),
      pht('This alias is already in use.'),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorPhurlURLReplyHandler())
      ->setMailReceiver($object);
  }

}
