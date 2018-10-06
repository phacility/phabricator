<?php

final class PholioMockEditor extends PhabricatorApplicationTransactionEditor {

  private $newImages = array();

  public function getEditorApplicationClass() {
    return 'PhabricatorPholioApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Pholio Mocks');
  }

  private function setNewImages(array $new_images) {
    assert_instances_of($new_images, 'PholioImage');
    $this->newImages = $new_images;
    return $this;
  }

  public function getNewImages() {
    return $this->newImages;
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this mock.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function shouldApplyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PholioImageFileTransaction::TRANSACTIONTYPE:
        case PholioImageReplaceTransaction::TRANSACTIONTYPE:
          return true;
          break;
      }
    }
    return false;
  }

  protected function applyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $new_images = array();
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PholioImageFileTransaction::TRANSACTIONTYPE:
          $new_value = $xaction->getNewValue();
          foreach ($new_value as $key => $txn_images) {
            if ($key != '+') {
              continue;
            }
            foreach ($txn_images as $image) {
              $image->save();
              $new_images[] = $image;
            }
          }
          break;
        case PholioImageReplaceTransaction::TRANSACTIONTYPE:
          $image = $xaction->getNewValue();
          $image->save();
          $new_images[] = $image;
          break;
      }
    }
    $this->setNewImages($new_images);
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $images = $this->getNewImages();
    foreach ($images as $image) {
      $image->setMockID($object->getID());
      $image->save();
    }

    return $xactions;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PholioReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("M{$id}: {$name}");
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

    $viewer = $this->requireActor();

    $body = id(new PhabricatorMetaMTAMailBody())
      ->setViewer($viewer);

    $mock_uri = $object->getURI();
    $mock_uri = PhabricatorEnv::getProductionURI($mock_uri);

    $this->addHeadersAndCommentsToMailBody(
      $body,
      $xactions,
      pht('View Mock'),
      $mock_uri);

    $type_inline = PholioMockInlineTransaction::TRANSACTIONTYPE;

    $inlines = array();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_inline) {
        $inlines[] = $xaction;
      }
    }

    $this->appendInlineCommentsForMail($object, $inlines, $body);

    $body->addLinkSection(
      pht('MOCK DETAIL'),
      PhabricatorEnv::getProductionURI('/M'.$object->getID()));

    return $body;
  }

  private function appendInlineCommentsForMail(
    $object,
    array $inlines,
    PhabricatorMetaMTAMailBody $body) {

    if (!$inlines) {
      return;
    }

    $viewer = $this->requireActor();

    $header = pht('INLINE COMMENTS');
    $body->addRawPlaintextSection($header);
    $body->addRawHTMLSection(phutil_tag('strong', array(), $header));

    $image_ids = array();
    foreach ($inlines as $inline) {
      $comment = $inline->getComment();
      $image_id = $comment->getImageID();
      $image_ids[$image_id] = $image_id;
    }

    $images = id(new PholioImageQuery())
      ->setViewer($viewer)
      ->withIDs($image_ids)
      ->execute();
    $images = mpull($images, null, 'getID');

    foreach ($inlines as $inline) {
      $comment = $inline->getComment();
      $content = $comment->getContent();
      $image_id = $comment->getImageID();
      $image = idx($images, $image_id);
      if ($image) {
        $image_name = $image->getName();
      } else {
        $image_name = pht('Unknown (ID %d)', $image_id);
      }

      $body->addRemarkupSection(
        pht('Image "%s":', $image_name),
        $content);
    }
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.pholio.subject-prefix');
  }

  public function getMailTagsMap() {
    return array(
      PholioTransaction::MAILTAG_STATUS =>
        pht("A mock's status changes."),
      PholioTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a mock.'),
      PholioTransaction::MAILTAG_UPDATED =>
        pht('Mock images or descriptions change.'),
      PholioTransaction::MAILTAG_OTHER =>
        pht('Other mock activity not listed above occurs.'),
    );
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
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

    return id(new HeraldPholioMockAdapter())
      ->setMock($object);
  }

  protected function sortTransactions(array $xactions) {
    $head = array();
    $tail = array();

    // Move inline comments to the end, so the comments precede them.
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == PholioMockInlineTransaction::TRANSACTIONTYPE) {
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
      case PholioMockInlineTransaction::TRANSACTIONTYPE:
        return true;
    }

    return parent::shouldImplyCC($object, $xaction);
  }

}
