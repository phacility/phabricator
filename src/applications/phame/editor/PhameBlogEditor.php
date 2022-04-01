<?php

final class PhameBlogEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phame Blogs');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this blog.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_INTERACT_POLICY;

    return $types;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

   protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    $phids[] = $this->requireActor()->getPHID();
    $phids[] = $object->getCreatorPHID();

    return $phids;
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name);
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhameBlogReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addLinkSection(
      pht('BLOG DETAIL'),
      PhabricatorEnv::getProductionURI($object->getViewURI()));

    return $body;
  }

  public function getMailTagsMap() {
    return array(
      PhameBlogTransaction::MAILTAG_DETAILS =>
        pht("A blog's details change."),
      PhameBlogTransaction::MAILTAG_SUBSCRIBERS =>
        pht("A blog's subscribers change."),
      PhameBlogTransaction::MAILTAG_OTHER =>
        pht('Other blog activity not listed above occurs.'),
    );
  }

  protected function getMailSubjectPrefix() {
    return '[Phame]';
  }


  protected function supportsSearch() {
    return true;
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return id(new HeraldPhameBlogAdapter())
      ->setBlog($object);
  }

}
