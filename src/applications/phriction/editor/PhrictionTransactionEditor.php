<?php

final class PhrictionTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  const VALIDATE_CREATE_ANCESTRY = 'create';
  const VALIDATE_MOVE_ANCESTRY   = 'move';

  private $description;
  private $oldContent;
  private $newContent;
  private $moveAwayDocument;
  private $skipAncestorCheck;
  private $contentVersion;
  private $processContentVersionError = true;
  private $contentDiffURI;

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

  public function getOldContent() {
    return $this->oldContent;
  }

  private function setNewContent(PhrictionContent $content) {
    $this->newContent = $content;
    return $this;
  }

  public function getNewContent() {
    return $this->newContent;
  }

  public function setSkipAncestorCheck($bool) {
    $this->skipAncestorCheck = $bool;
    return $this;
  }

  public function getSkipAncestorCheck() {
    return $this->skipAncestorCheck;
  }

  public function setContentVersion($version) {
    $this->contentVersion = $version;
    return $this;
  }

  public function getContentVersion() {
    return $this->contentVersion;
  }

  public function setProcessContentVersionError($process) {
    $this->processContentVersionError = $process;
    return $this;
  }

  public function getProcessContentVersionError() {
    return $this->processContentVersionError;
  }

  public function setMoveAwayDocument(PhrictionDocument $document) {
    $this->moveAwayDocument = $document;
    return $this;
  }

  public function setShouldPublishContent(
    PhrictionDocument $object,
    $publish) {

    if ($publish) {
      $content_phid = $this->getNewContent()->getPHID();
    } else {
      $content_phid = $this->getOldContent()->getPHID();
    }

    $object->setContentPHID($content_phid);

    return $this;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phriction Documents');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function expandTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $this->setOldContent($object->getContent());

    return parent::expandTransactions($object, $xactions);
  }

  protected function expandTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $xactions = parent::expandTransaction($object, $xaction);
    switch ($xaction->getTransactionType()) {
      case PhrictionDocumentContentTransaction::TRANSACTIONTYPE:
        if ($this->getIsNewObject()) {
          break;
        }
        $content = $xaction->getNewValue();
        if ($content === '') {
          $xactions[] = id(new PhrictionTransaction())
            ->setTransactionType(
              PhrictionDocumentDeleteTransaction::TRANSACTIONTYPE)
            ->setNewValue(true)
            ->setMetadataValue('contentDelete', true);
        }
        break;
      case PhrictionDocumentMoveToTransaction::TRANSACTIONTYPE:
        $document = $xaction->getNewValue();
        $xactions[] = id(new PhrictionTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
          ->setNewValue($document->getViewPolicy());
        $xactions[] = id(new PhrictionTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
          ->setNewValue($document->getEditPolicy());
        break;
      default:
        break;
    }

    return $xactions;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if ($this->hasNewDocumentContent()) {
      $content = $this->getNewDocumentContent($object);

      $content
        ->setDocumentPHID($object->getPHID())
        ->save();
    }

    if ($this->getIsNewObject() && !$this->getSkipAncestorCheck()) {
      // Stub out empty parent documents if they don't exist
      $ancestral_slugs = PhabricatorSlug::getAncestry($object->getSlug());
      if ($ancestral_slugs) {
        $ancestors = id(new PhrictionDocumentQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withSlugs($ancestral_slugs)
          ->needContent(true)
          ->execute();
        $ancestors = mpull($ancestors, null, 'getSlug');
        $stub_type = PhrictionChangeType::CHANGE_STUB;
        foreach ($ancestral_slugs as $slug) {
          $ancestor_doc = idx($ancestors, $slug);
          // We check for change type to prevent near-infinite recursion
          if (!$ancestor_doc && $content->getChangeType() != $stub_type) {
            $ancestor_doc = PhrictionDocument::initializeNewDocument(
              $this->getActor(),
              $slug);
            $stub_xactions = array();
            $stub_xactions[] = id(new PhrictionTransaction())
              ->setTransactionType(
                PhrictionDocumentTitleTransaction::TRANSACTIONTYPE)
              ->setNewValue(PhabricatorSlug::getDefaultTitle($slug))
              ->setMetadataValue('stub:create:phid', $object->getPHID());
            $stub_xactions[] = id(new PhrictionTransaction())
              ->setTransactionType(
                PhrictionDocumentContentTransaction::TRANSACTIONTYPE)
              ->setNewValue('')
              ->setMetadataValue('stub:create:phid', $object->getPHID());
            $stub_xactions[] = id(new PhrictionTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
              ->setNewValue($object->getViewPolicy());
            $stub_xactions[] = id(new PhrictionTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
              ->setNewValue($object->getEditPolicy());
            $sub_editor = id(new PhrictionTransactionEditor())
              ->setActor($this->getActor())
              ->setContentSource($this->getContentSource())
              ->setContinueOnNoEffect($this->getContinueOnNoEffect())
              ->setSkipAncestorCheck(true)
              ->setDescription(pht('Empty Parent Document'))
              ->applyTransactions($ancestor_doc, $stub_xactions);
          }
        }
      }
    }

    if ($this->moveAwayDocument !== null) {
      $move_away_xactions = array();
      $move_away_xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(
          PhrictionDocumentMoveAwayTransaction::TRANSACTIONTYPE)
        ->setNewValue($object);
      $sub_editor = id(new PhrictionTransactionEditor())
        ->setActor($this->getActor())
        ->setContentSource($this->getContentSource())
        ->setContinueOnNoEffect($this->getContinueOnNoEffect())
        ->setDescription($this->getDescription())
        ->applyTransactions($this->moveAwayDocument, $move_away_xactions);
    }

    // Compute the content diff URI for the publishing phase.
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhrictionDocumentContentTransaction::TRANSACTIONTYPE:
          $params = array(
            'l' => $this->getOldContent()->getVersion(),
            'r' => $this->getNewContent()->getVersion(),
          );

          $path = '/phriction/diff/'.$object->getID().'/';
          $uri = new PhutilURI($path, $params);

          $this->contentDiffURI = (string)$uri;
          break 2;
        default:
          break;
      }
    }

    return $xactions;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return '[Phriction]';
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $this->getActingAsPHID(),
    );
  }

  public function getMailTagsMap() {
    return array(
      PhrictionTransaction::MAILTAG_TITLE =>
        pht("A document's title changes."),
      PhrictionTransaction::MAILTAG_CONTENT =>
        pht("A document's content changes."),
      PhrictionTransaction::MAILTAG_DELETE =>
        pht('A document is deleted.'),
      PhrictionTransaction::MAILTAG_SUBSCRIBERS =>
        pht('A document\'s subscribers change.'),
      PhrictionTransaction::MAILTAG_OTHER =>
        pht('Other document activity not listed above occurs.'),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhrictionReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $title = $object->getContent()->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($title);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    if ($this->getIsNewObject()) {
      $body->addRemarkupSection(
        pht('DOCUMENT CONTENT'),
        $object->getContent()->getContent());
    } else if ($this->contentDiffURI) {
      $body->addLinkSection(
        pht('DOCUMENT DIFF'),
        PhabricatorEnv::getProductionURI($this->contentDiffURI));
    }

    $description = $object->getContent()->getDescription();
    if ($description !== null && strlen($description)) {
      $body->addTextSection(
        pht('EDIT NOTES'),
        $description);
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

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhrictionDocumentMoveToTransaction::TRANSACTIONTYPE:
          $dict = $xaction->getNewValue();
          $phids[] = $dict['phid'];
          break;
      }
    }

    return $phids;
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    foreach ($xactions as $xaction) {
      switch ($type) {
        case PhrictionDocumentContentTransaction::TRANSACTIONTYPE:
          if ($xaction->getMetadataValue('stub:create:phid')) {
            break;
          }

          if ($this->getProcessContentVersionError()) {
            $error = $this->validateContentVersion($object, $type, $xaction);
            if ($error) {
              $this->setProcessContentVersionError(false);
              $errors[] = $error;
            }
          }

          if ($this->getIsNewObject()) {
            $ancestry_errors = $this->validateAncestry(
              $object,
              $type,
              $xaction,
              self::VALIDATE_CREATE_ANCESTRY);
            if ($ancestry_errors) {
              $errors = array_merge($errors, $ancestry_errors);
            }
          }
          break;

        case PhrictionDocumentMoveToTransaction::TRANSACTIONTYPE:
          $source_document = $xaction->getNewValue();

          $ancestry_errors = $this->validateAncestry(
            $object,
            $type,
            $xaction,
            self::VALIDATE_MOVE_ANCESTRY);
          if ($ancestry_errors) {
            $errors = array_merge($errors, $ancestry_errors);
          }

          $target_document = id(new PhrictionDocumentQuery())
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->withSlugs(array($object->getSlug()))
            ->needContent(true)
            ->executeOne();

          // Prevent overwrites and no-op moves.
          $exists = PhrictionDocumentStatus::STATUS_EXISTS;
          if ($target_document) {
            $message = null;
            if ($target_document->getSlug() == $source_document->getSlug()) {
              $message = pht(
                'You can not move a document to its existing location. '.
                'Choose a different location to move the document to.');
            } else if ($target_document->getStatus() == $exists) {
              $message = pht(
                'You can not move this document there, because it would '.
                'overwrite an existing document which is already at that '.
                'location. Move or delete the existing document first.');
            }
            if ($message !== null) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                $message,
                $xaction);
              $errors[] = $error;
            }
          }
          break;

      }
    }

    return $errors;
  }

  public function validateAncestry(
    PhabricatorLiskDAO $object,
    $type,
    PhabricatorApplicationTransaction $xaction,
    $verb) {

    $errors = array();
    // NOTE: We use the omnipotent user for these checks because policy
    // doesn't matter; existence does.
    $other_doc_viewer = PhabricatorUser::getOmnipotentUser();
    $ancestral_slugs = PhabricatorSlug::getAncestry($object->getSlug());
    if ($ancestral_slugs) {
      $ancestors = id(new PhrictionDocumentQuery())
        ->setViewer($other_doc_viewer)
        ->withSlugs($ancestral_slugs)
        ->execute();
      $ancestors = mpull($ancestors, null, 'getSlug');
      foreach ($ancestral_slugs as $slug) {
        $ancestor_doc = idx($ancestors, $slug);
        if (!$ancestor_doc) {
          $create_uri = '/phriction/edit/?slug='.$slug;
          $create_link = phutil_tag(
            'a',
            array(
              'href' => $create_uri,
            ),
            $slug);
          switch ($verb) {
            case self::VALIDATE_MOVE_ANCESTRY:
              $message = pht(
                'Can not move document because the parent document with '.
                'slug %s does not exist!',
                $create_link);
              break;
            case self::VALIDATE_CREATE_ANCESTRY:
              $message = pht(
                'Can not create document because the parent document with '.
                'slug %s does not exist!',
                $create_link);
              break;
          }
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Missing Ancestor'),
            $message,
            $xaction);
          $errors[] = $error;
        }
      }
    }
    return $errors;
  }

  private function validateContentVersion(
    PhabricatorLiskDAO $object,
    $type,
    PhabricatorApplicationTransaction $xaction) {

    $error = null;
    if ($this->getContentVersion() &&
       ($object->getMaxVersion() != $this->getContentVersion())) {
      $error = new PhabricatorApplicationTransactionValidationError(
        $type,
        pht('Edit Conflict'),
        pht(
          'Another user made changes to this document after you began '.
          'editing it. Do you want to overwrite their changes? '.
          '(If you choose to overwrite their changes, you should review '.
          'the document edit history to see what you overwrote, and '.
          'then make another edit to merge the changes if necessary.)'),
        $xaction);
    }
    return $error;
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

    return id(new PhrictionDocumentHeraldAdapter())
      ->setDocument($object);
  }

  private function hasNewDocumentContent() {
    return (bool)$this->newContent;
  }

  public function getNewDocumentContent(PhrictionDocument $document) {
    if (!$this->hasNewDocumentContent()) {
      $content = $this->newDocumentContent($document);

      // Generate a PHID now so we can populate "contentPHID" before saving
      // the document to the database: the column is not nullable so we need
      // a value.
      $content_phid = $content->generatePHID();

      $content->setPHID($content_phid);

      $document->setContentPHID($content_phid);
      $document->attachContent($content);
      $document->setEditedEpoch(PhabricatorTime::getNow());
      $document->setMaxVersion($content->getVersion());

      $this->newContent = $content;
    }

    return $this->newContent;
  }

  private function newDocumentContent(PhrictionDocument $document) {
    $content = id(new PhrictionContent())
      ->setSlug($document->getSlug())
      ->setAuthorPHID($this->getActingAsPHID())
      ->setChangeType(PhrictionChangeType::CHANGE_EDIT)
      ->setTitle($this->getOldContent()->getTitle())
      ->setContent($this->getOldContent()->getContent())
      ->setDescription('');

    if ($this->getDescription() !== null && strlen($this->getDescription())) {
      $content->setDescription($this->getDescription());
    }

    $content->setVersion($document->getMaxVersion() + 1);

    return $content;
  }

  protected function getCustomWorkerState() {
    return array(
      'contentDiffURI' => $this->contentDiffURI,
    );
  }

  protected function loadCustomWorkerState(array $state) {
    $this->contentDiffURI = idx($state, 'contentDiffURI');
    return $this;
  }

}
