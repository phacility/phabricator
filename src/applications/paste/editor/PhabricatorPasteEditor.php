<?php

final class PhabricatorPasteEditor
  extends PhabricatorApplicationTransactionEditor {

  private $newPasteTitle;

  public function getNewPasteTitle() {
    return $this->newPasteTitle;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorPasteApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Pastes');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this paste.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    return $types;
  }

  protected function expandTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $new_title = $object->getTitle();
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type === PhabricatorPasteTitleTransaction::TRANSACTIONTYPE) {
        $new_title = $xaction->getNewValue();
      }
    }
    $this->newPasteTitle = $new_title;

    return parent::expandTransactions($object, $xactions);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if ($this->getIsNewObject()) {
      return false;
    }

    return true;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Paste]');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->getActingAsPHID(),
    );
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorPasteTransaction::MAILTAG_CONTENT =>
        pht('Paste title, language or text changes.'),
      PhabricatorPasteTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a paste.'),
      PhabricatorPasteTransaction::MAILTAG_OTHER =>
        pht('Other paste activity not listed above occurs.'),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PasteReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("P{$id}: {$name}");
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addLinkSection(
      pht('PASTE DETAIL'),
      PhabricatorEnv::getProductionURI('/P'.$object->getID()));

    return $body;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

}
