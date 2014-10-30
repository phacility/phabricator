<?php

final class PhrictionTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  private $description;
  private $oldContent;
  private $newContent;

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  private function getDescription() {
    return $this->description;
  }

  private function setOldContent(PhrictionContent $content) {
    $this->oldContent = $content;
    return $this;
  }

  private function getOldContent() {
    return $this->oldContent;
  }

  private function setNewContent(PhrictionContent $content) {
    $this->newContent = $content;
    return $this;
  }

  private function getNewContent() {
    return $this->newContent;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phriction Documents');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhrictionTransaction::TYPE_TITLE;
    $types[] = PhrictionTransaction::TYPE_CONTENT;

    /* TODO
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
     */

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhrictionTransaction::TYPE_TITLE:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $this->getOldContent()->getTitle();
      case PhrictionTransaction::TYPE_CONTENT:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $this->getOldContent()->getContent();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhrictionTransaction::TYPE_TITLE:
      case PhrictionTransaction::TYPE_CONTENT:
        return $xaction->getNewValue();
    }
  }

  protected function shouldApplyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
      case PhrictionTransaction::TYPE_TITLE:
      case PhrictionTransaction::TYPE_CONTENT:
        return true;
      }
    }
    return parent::shouldApplyInitialEffects($object, $xactions);
  }

  protected function applyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $this->setOldContent($object->getContent());
    $this->setNewContent($this->buildNewContentTemplate($object));
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhrictionTransaction::TYPE_TITLE:
      case PhrictionTransaction::TYPE_CONTENT:
        $object->setStatus(PhrictionDocumentStatus::STATUS_EXISTS);
        return;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhrictionTransaction::TYPE_TITLE:
        $this->getNewContent()->setTitle($xaction->getNewValue());
        break;
      case PhrictionTransaction::TYPE_CONTENT:
        $this->getNewContent()->setContent($xaction->getNewValue());
        break;
      default:
        break;
    }
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $save_content = false;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhrictionTransaction::TYPE_TITLE:
        case PhrictionTransaction::TYPE_CONTENT:
          $save_content = true;
          break;
        default:
          break;
      }
    }

    if ($save_content) {
      $content = $this->getNewContent();
      $content->setDocumentID($object->getID());
      $content->save();

      $object->setContentID($content->getID());
      $object->save();
      $object->attachContent($content);
    }

    if ($this->getIsNewObject()) {
      // Stub out empty parent documents if they don't exist
      $ancestral_slugs = PhabricatorSlug::getAncestry($object->getSlug());
      if ($ancestral_slugs) {
        $ancestors = id(new PhrictionDocument())->loadAllWhere(
          'slug IN (%Ls)',
          $ancestral_slugs);
        $ancestors = mpull($ancestors, null, 'getSlug');
        foreach ($ancestral_slugs as $slug) {
          // We check for change type to prevent near-infinite recursion
          if (!isset($ancestors[$slug]) &&
            $content->getChangeType() !=
            PhrictionChangeType::CHANGE_STUB) {
              id(PhrictionDocumentEditor::newForSlug($slug))
                ->setActor($this->getActor())
                ->setTitle(PhabricatorSlug::getDefaultTitle($slug))
                ->setContent('')
                ->setDescription(pht('Empty Parent Document'))
                ->stub();
            }
        }
      }
    }
    return $xactions;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $xactions = mfilter($xactions, 'shouldHide', true);
    return $xactions;
  }

  protected function getMailSubjectPrefix() {
    return '[Phriction]';
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getContent()->getAuthorPHID(),
      $this->getActingAsPHID(),
    );
  }

  public function getMailTagsMap() {
    return array(
      PhrictionTransaction::MAILTAG_TITLE =>
        pht("A document's title changes."),
      PhrictionTransaction::MAILTAG_CONTENT =>
        pht("A document's content changes."),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhrictionReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getContent()->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($title)
      ->addHeader('Thread-Topic', $object->getPHID());
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    if ($this->getIsNewObject()) {
      $body->addTextSection(
        pht('DOCUMENT CONTENT'),
        $object->getContent()->getContent());
    }

    $body->addLinkSection(
      pht('DOCUMENT DETAIL'),
      PhabricatorEnv::getProductionURI(
        PhrictionDocument::getSlugURI($object->getSlug())));

    return $body;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $this->shouldSendMail($object, $xactions);
  }

  protected function getFeedRelatedPHIDs(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $phids = parent::getFeedRelatedPHIDs($object, $xactions);
    // TODO - once the editor supports moves, we'll need to surface the
    // "from document phid" to related phids.
    return $phids;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  private function buildNewContentTemplate(
    PhrictionDocument $document) {

    $new_content = new PhrictionContent();
    $new_content->setSlug($document->getSlug());
    $new_content->setAuthorPHID($this->getActor()->getPHID());
    $new_content->setChangeType(PhrictionChangeType::CHANGE_EDIT);

    $new_content->setTitle($this->getOldContent()->getTitle());
    $new_content->setContent($this->getOldContent()->getContent());

    if (strlen($this->getDescription())) {
      $new_content->setDescription($this->getDescription());
    }
    $new_content->setVersion($this->getOldContent()->getVersion() + 1);

    return $new_content;
  }

}
