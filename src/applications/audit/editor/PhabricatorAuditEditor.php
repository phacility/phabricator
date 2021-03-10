<?php

final class PhabricatorAuditEditor
  extends PhabricatorApplicationTransactionEditor {

  const MAX_FILES_SHOWN_IN_EMAIL = 1000;

  private $affectedFiles;
  private $rawPatch;
  private $auditorPHIDs = array();

  private $didExpandInlineState = false;
  private $oldAuditStatus = null;

  public function setRawPatch($patch) {
    $this->rawPatch = $patch;
    return $this;
  }

  public function getRawPatch() {
    return $this->rawPatch;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Audits');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_INLINESTATE;

    $types[] = PhabricatorAuditTransaction::TYPE_COMMIT;

    // TODO: These will get modernized eventually, but that can happen one
    // at a time later on.
    $types[] = PhabricatorAuditActionConstants::INLINE;

    return $types;
  }

  protected function expandTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_INLINESTATE:
          $this->didExpandInlineState = true;
          break;
      }
    }

    $this->oldAuditStatus = $object->getAuditStatus();

    return parent::expandTransactions($object, $xactions);
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
        return $xaction->hasComment();
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditTransaction::TYPE_COMMIT:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditTransaction::TYPE_COMMIT:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
        $comment = $xaction->getComment();

        $comment->setAttribute('editing', false);

        PhabricatorVersionedDraft::purgeDrafts(
          $comment->getPHID(),
          $this->getActingAsPHID());
        return;
      case PhabricatorAuditTransaction::TYPE_COMMIT:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditTransaction::TYPE_COMMIT:
        return;
      case PhabricatorAuditActionConstants::INLINE:
        $reply = $xaction->getComment()->getReplyToComment();
        if ($reply && !$reply->getHasReplies()) {
          $reply->setHasReplies(1)->save();
        }
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function applyBuiltinExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_INLINESTATE:
        $table = new PhabricatorAuditTransactionComment();
        $conn_w = $table->establishConnection('w');
        foreach ($xaction->getNewValue() as $phid => $state) {
          queryfx(
            $conn_w,
            'UPDATE %T SET fixedState = %s WHERE phid = %s',
            $table->getTableName(),
            $state,
            $phid);
        }
        break;
    }

    return parent::applyBuiltinExternalTransaction($object, $xaction);
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // Load auditors explicitly; we may not have them if the caller was a
    // generic piece of infrastructure.

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($this->requireActor())
      ->withIDs(array($object->getID()))
      ->needAuditRequests(true)
      ->executeOne();
    if (!$commit) {
      throw new Exception(
        pht('Failed to load commit during transaction finalization!'));
    }
    $object->attachAudits($commit->getAudits());

    $actor_phid = $this->getActingAsPHID();
    $actor_is_author = ($object->getAuthorPHID()) &&
      ($actor_phid == $object->getAuthorPHID());

    $import_status_flag = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorAuditTransaction::TYPE_COMMIT:
          $import_status_flag = PhabricatorRepositoryCommit::IMPORTED_PUBLISH;
          break;
      }
    }

    $old_status = $this->oldAuditStatus;

    $requests = $object->getAudits();
    $object->updateAuditStatus($requests);

    $new_status = $object->getAuditStatus();

    $object->save();

    if ($import_status_flag) {
      $object->writeImportStatusFlag($import_status_flag);
    }

    // If the commit has changed state after this edit, add an informational
    // transaction about the state change.
    if ($old_status != $new_status) {
      if ($object->isAuditStatusPartiallyAudited()) {
        // This state isn't interesting enough to get a transaction. The
        // best way we could lead the user forward is something like "This
        // commit still requires additional audits." but that's redundant and
        // probably not very useful.
      } else {
        $xaction = $object->getApplicationTransactionTemplate()
          ->setTransactionType(DiffusionCommitStateTransaction::TRANSACTIONTYPE)
          ->setOldValue($old_status)
          ->setNewValue($new_status);

        $xaction = $this->populateTransaction($object, $xaction);

        $xaction->save();
      }
    }

    // Collect auditor PHIDs for building mail.
    $this->auditorPHIDs = mpull($object->getAudits(), 'getAuditorPHID');

    return $xactions;
  }

  protected function expandTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $auditors_type = DiffusionCommitAuditorsTransaction::TRANSACTIONTYPE;

    $xactions = parent::expandTransaction($object, $xaction);

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditTransaction::TYPE_COMMIT:
        $phids = $this->getAuditRequestTransactionPHIDsFromCommitMessage(
          $object);
        if ($phids) {
          $xactions[] = $object->getApplicationTransactionTemplate()
            ->setTransactionType($auditors_type)
            ->setNewValue(
              array(
                '+' => array_fuse($phids),
              ));
          $this->addUnmentionablePHIDs($phids);
        }
        break;
      default:
        break;
    }

    if (!$this->didExpandInlineState) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_COMMENT:
          $this->didExpandInlineState = true;

          $query_template = id(new DiffusionDiffInlineCommentQuery())
            ->withCommitPHIDs(array($object->getPHID()));

          $state_xaction = $this->newInlineStateTransaction(
            $object,
            $query_template);

          if ($state_xaction) {
            $xactions[] = $state_xaction;
          }
          break;
      }
    }

    return $xactions;
  }

  private function getAuditRequestTransactionPHIDsFromCommitMessage(
    PhabricatorRepositoryCommit $commit) {

    $actor = $this->getActor();
    $data = $commit->getCommitData();
    $message = $data->getCommitMessage();

    $result = DifferentialCommitMessageParser::newStandardParser($actor)
      ->setRaiseMissingFieldErrors(false)
      ->parseFields($message);

    $field_key = DifferentialAuditorsCommitMessageField::FIELDKEY;
    $phids = idx($result, $field_key, null);

    if (!$phids) {
      return array();
    }

    // If a commit lists its author as an auditor, just pretend it does not.
    foreach ($phids as $key => $phid) {
      if ($phid == $commit->getAuthorPHID()) {
        unset($phids[$key]);
      }
    }

    if (!$phids) {
      return array();
    }

    return $phids;
  }

  protected function sortTransactions(array $xactions) {
    $xactions = parent::sortTransactions($xactions);

    $head = array();
    $tail = array();

    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == PhabricatorAuditActionConstants::INLINE) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }

  protected function supportsSearch() {
    return true;
  }

  protected function expandCustomRemarkupBlockTransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    array $changes,
    PhutilMarkupEngine $engine) {

    $actor = $this->getActor();
    $result = array();

    // Some interactions (like "Fixes Txxx" interacting with Maniphest) have
    // already been processed, so we're only re-parsing them here to avoid
    // generating an extra redundant mention. Other interactions are being
    // processed for the first time.

    // We're only recognizing magic in the commit message itself, not in
    // audit comments.

    $is_commit = false;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorAuditTransaction::TYPE_COMMIT:
          $is_commit = true;
          break;
      }
    }

    if (!$is_commit) {
      return $result;
    }

    $flat_blocks = mpull($changes, 'getNewValue');
    $huge_block = implode("\n\n", $flat_blocks);
    $phid_map = array();
    $monograms = array();

    $task_refs = id(new ManiphestCustomFieldStatusParser())
      ->parseCorpus($huge_block);
    foreach ($task_refs as $match) {
      foreach ($match['monograms'] as $monogram) {
        $monograms[] = $monogram;
      }
    }

    $rev_refs = id(new DifferentialCustomFieldDependsOnParser())
      ->parseCorpus($huge_block);
    foreach ($rev_refs as $match) {
      foreach ($match['monograms'] as $monogram) {
        $monograms[] = $monogram;
      }
    }

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getActor())
      ->withNames($monograms)
      ->execute();
    $phid_map[] = mpull($objects, 'getPHID', 'getPHID');

    $reverts_refs = id(new DifferentialCustomFieldRevertsParser())
      ->parseCorpus($huge_block);
    $reverts = array_mergev(ipull($reverts_refs, 'monograms'));
    if ($reverts) {
      $reverted_objects = DiffusionCommitRevisionQuery::loadRevertedObjects(
        $actor,
        $object,
        $reverts,
        $object->getRepository());

      $reverted_phids = mpull($reverted_objects, 'getPHID', 'getPHID');

      $reverts_edge = DiffusionCommitRevertsCommitEdgeType::EDGECONST;
      $result[] = id(new PhabricatorAuditTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $reverts_edge)
        ->setNewValue(array('+' => $reverted_phids));

      $phid_map[] = $reverted_phids;
    }

    // See T13463. Copy "related task" edges from the associated revision, if
    // one exists.

    $revision = DiffusionCommitRevisionQuery::loadRevisionForCommit(
      $actor,
      $object);
    if ($revision) {
      $task_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $revision->getPHID(),
        DifferentialRevisionHasTaskEdgeType::EDGECONST);
      $task_phids = array_fuse($task_phids);

      if ($task_phids) {
        $related_edge = DiffusionCommitHasTaskEdgeType::EDGECONST;
        $result[] = id(new PhabricatorAuditTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $related_edge)
          ->setNewValue(array('+' => $task_phids));
      }

      // Mark these objects as unmentionable, since the explicit relationship
      // is stronger and any mentions are redundant.
      $phid_map[] = $task_phids;
    }

    $phid_map = array_mergev($phid_map);
    $this->addUnmentionablePHIDs($phid_map);

    return $result;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    $reply_handler = new PhabricatorAuditReplyHandler();
    $reply_handler->setMailReceiver($object);
    return $reply_handler;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Diffusion]');
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    // For backward compatibility, use this legacy thread ID.
    return 'diffusion-audit-'.$object->getPHID();
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $identifier = $object->getCommitIdentifier();
    $repository = $object->getRepository();

    $summary = $object->getSummary();
    $name = $repository->formatCommitName($identifier);

    $subject = "{$name}: {$summary}";

    $template = id(new PhabricatorMetaMTAMail())
      ->setSubject($subject);

    $this->attachPatch(
      $template,
      $object);

    return $template;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $this->requireAuditors($object);

    $phids = array();

    if ($object->getAuthorPHID()) {
      $phids[] = $object->getAuthorPHID();
    }

    foreach ($object->getAudits() as $audit) {
      if (!$audit->isResigned()) {
        $phids[] = $audit->getAuditorPHID();
      }
    }

    $phids[] = $this->getActingAsPHID();

    return $phids;
  }

  protected function newMailUnexpandablePHIDs(PhabricatorLiskDAO $object) {
    $this->requireAuditors($object);

    $phids = array();

    foreach ($object->getAudits() as $auditor) {
      if ($auditor->isResigned()) {
        $phids[] = $auditor->getAuditorPHID();
      }
    }

    return $phids;
  }

  protected function getObjectLinkButtonLabelForMail(
    PhabricatorLiskDAO $object) {
    return pht('View Commit');
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $type_inline = PhabricatorAuditActionConstants::INLINE;
    $type_push = PhabricatorAuditTransaction::TYPE_COMMIT;

    $is_commit = false;
    $inlines = array();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_inline) {
        $inlines[] = $xaction;
      }
      if ($xaction->getTransactionType() == $type_push) {
        $is_commit = true;
      }
    }

    if ($inlines) {
      $body->addTextSection(
        pht('INLINE COMMENTS'),
        $this->renderInlineCommentsForMail($object, $inlines));
    }

    if ($is_commit) {
      $data = $object->getCommitData();
      $body->addTextSection(pht('AFFECTED FILES'), $this->affectedFiles);
      $this->inlinePatch(
        $body,
        $object);
    }

    $data = $object->getCommitData();

    $user_phids = array();

    $author_phid = $object->getAuthorPHID();
    if ($author_phid) {
      $user_phids[$author_phid][] = pht('Author');
    }

    $committer_phid = $data->getCommitDetail('committerPHID');
    if ($committer_phid && ($committer_phid != $author_phid)) {
      $user_phids[$committer_phid][] = pht('Committer');
    }

    foreach ($this->auditorPHIDs as $auditor_phid) {
      $user_phids[$auditor_phid][] = pht('Auditor');
    }

    // TODO: It would be nice to show pusher here too, but that information
    // is a little tricky to get at right now.

    if ($user_phids) {
      $handle_phids = array_keys($user_phids);
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireActor())
        ->withPHIDs($handle_phids)
        ->execute();

      $user_info = array();
      foreach ($user_phids as $phid => $roles) {
        $user_info[] = pht(
          '%s (%s)',
          $handles[$phid]->getName(),
          implode(', ', $roles));
      }

      $body->addTextSection(
        pht('USERS'),
        implode("\n", $user_info));
    }

    $monogram = $object->getRepository()->formatCommitName(
      $object->getCommitIdentifier());

    $body->addLinkSection(
      pht('COMMIT'),
      PhabricatorEnv::getProductionURI('/'.$monogram));

    return $body;
  }

  private function attachPatch(
    PhabricatorMetaMTAMail $template,
    PhabricatorRepositoryCommit $commit) {

    if (!$this->getRawPatch()) {
      return;
    }

    $attach_key = 'metamta.diffusion.attach-patches';
    $attach_patches = PhabricatorEnv::getEnvConfig($attach_key);
    if (!$attach_patches) {
      return;
    }

    $repository = $commit->getRepository();
    $encoding = $repository->getDetail('encoding', 'UTF-8');

    $raw_patch = $this->getRawPatch();
    $commit_name = $repository->formatCommitName(
      $commit->getCommitIdentifier());

    $template->addAttachment(
      new PhabricatorMailAttachment(
        $raw_patch,
        $commit_name.'.patch',
        'text/x-patch; charset='.$encoding));
  }

  private function inlinePatch(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorRepositoryCommit $commit) {

    if (!$this->getRawPatch()) {
        return;
    }

    $inline_key = 'metamta.diffusion.inline-patches';
    $inline_patches = PhabricatorEnv::getEnvConfig($inline_key);
    if (!$inline_patches) {
      return;
    }

    $repository = $commit->getRepository();
    $raw_patch = $this->getRawPatch();
    $result = null;
    $len = substr_count($raw_patch, "\n");
    if ($len <= $inline_patches) {
      // We send email as utf8, so we need to convert the text to utf8 if
      // we can.
      $encoding = $repository->getDetail('encoding', 'UTF-8');
      if ($encoding) {
        $raw_patch = phutil_utf8_convert($raw_patch, 'UTF-8', $encoding);
      }
      $result = phutil_utf8ize($raw_patch);
    }

    if ($result) {
      $result = "PATCH\n\n{$result}\n";
    }
    $body->addRawSection($result);
  }

  private function renderInlineCommentsForMail(
    PhabricatorLiskDAO $object,
    array $inline_xactions) {

    $inlines = mpull($inline_xactions, 'getComment');

    $block = array();

    $path_map = id(new DiffusionPathQuery())
      ->withPathIDs(mpull($inlines, 'getPathID'))
      ->execute();
    $path_map = ipull($path_map, 'path', 'id');

    foreach ($inlines as $inline) {
      $path = idx($path_map, $inline->getPathID());
      if ($path === null) {
        continue;
      }

      $start = $inline->getLineNumber();
      $len   = $inline->getLineLength();
      if ($len) {
        $range = $start.'-'.($start + $len);
      } else {
        $range = $start;
      }

      $content = $inline->getContent();
      $block[] = "{$path}:{$range} {$content}";
    }

    return implode("\n", $block);
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorAuditTransaction::MAILTAG_COMMIT =>
        pht('A commit is created.'),
      PhabricatorAuditTransaction::MAILTAG_ACTION_CONCERN =>
        pht('A commit has a concerned raised against it.'),
      PhabricatorAuditTransaction::MAILTAG_ACTION_ACCEPT =>
        pht('A commit is accepted.'),
      PhabricatorAuditTransaction::MAILTAG_ACTION_RESIGN =>
        pht('A commit has an auditor resign.'),
      PhabricatorAuditTransaction::MAILTAG_ACTION_CLOSE =>
        pht('A commit is closed.'),
      PhabricatorAuditTransaction::MAILTAG_ADD_AUDITORS =>
        pht('A commit has auditors added.'),
      PhabricatorAuditTransaction::MAILTAG_ADD_CCS =>
        pht("A commit's subscribers change."),
      PhabricatorAuditTransaction::MAILTAG_PROJECTS =>
        pht("A commit's projects change."),
      PhabricatorAuditTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a commit.'),
      PhabricatorAuditTransaction::MAILTAG_OTHER =>
        pht('Other commit activity not listed above occurs.'),
    );
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorAuditTransaction::TYPE_COMMIT:
          $repository = $object->getRepository();
          $publisher = $repository->newPublisher();
          if (!$publisher->shouldPublishCommit($object)) {
            return false;
          }
          return true;
        default:
          break;
      }
    }
    return parent::shouldApplyHeraldRules($object, $xactions);
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return id(new HeraldCommitAdapter())
      ->setObject($object);
  }

  protected function didApplyHeraldRules(
    PhabricatorLiskDAO $object,
    HeraldAdapter $adapter,
    HeraldTranscript $transcript) {

    $limit = self::MAX_FILES_SHOWN_IN_EMAIL;
    $files = $adapter->loadAffectedPaths();
    sort($files);
    if (count($files) > $limit) {
      array_splice($files, $limit);
      $files[] = pht(
        '(This commit affected more than %d files. Only %d are shown here '.
        'and additional ones are truncated.)',
        $limit,
        $limit);
    }
    $this->affectedFiles = implode("\n", $files);

    return array();
  }

  private function isCommitMostlyImported(PhabricatorLiskDAO $object) {
    $has_message = PhabricatorRepositoryCommit::IMPORTED_MESSAGE;
    $has_changes = PhabricatorRepositoryCommit::IMPORTED_CHANGE;

    // Don't publish feed stories or email about events which occur during
    // import. In particular, this affects tasks being attached when they are
    // closed by "Fixes Txxxx" in a commit message. See T5851.

    $mask = ($has_message | $has_changes);

    return $object->isPartiallyImported($mask);
  }


  private function shouldPublishRepositoryActivity(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // not every code path loads the repository so tread carefully
    // TODO: They should, and then we should simplify this.
    $repository = $object->getRepository($assert_attached = false);
    if ($repository != PhabricatorLiskDAO::ATTACHABLE) {
      $publisher = $repository->newPublisher();
      if (!$publisher->shouldPublishCommit($object)) {
        return false;
      }
    }

    return $this->isCommitMostlyImported($object);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $this->shouldPublishRepositoryActivity($object, $xactions);
  }

  protected function shouldEnableMentions(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $this->shouldPublishRepositoryActivity($object, $xactions);
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $this->shouldPublishRepositoryActivity($object, $xactions);
  }

  protected function getCustomWorkerState() {
    return array(
      'rawPatch' => $this->rawPatch,
      'affectedFiles' => $this->affectedFiles,
      'auditorPHIDs' => $this->auditorPHIDs,
    );
  }

  protected function getCustomWorkerStateEncoding() {
    return array(
      'rawPatch' => self::STORAGE_ENCODING_BINARY,
    );
  }

  protected function loadCustomWorkerState(array $state) {
    $this->rawPatch = idx($state, 'rawPatch');
    $this->affectedFiles = idx($state, 'affectedFiles');
    $this->auditorPHIDs = idx($state, 'auditorPHIDs');
    return $this;
  }

  protected function willPublish(PhabricatorLiskDAO $object, array $xactions) {
    return id(new DiffusionCommitQuery())
      ->setViewer($this->requireActor())
      ->withIDs(array($object->getID()))
      ->needAuditRequests(true)
      ->needCommitData(true)
      ->executeOne();
  }

  private function requireAuditors(PhabricatorRepositoryCommit $commit) {
    if ($commit->hasAttachedAudits()) {
      return;
    }

    $with_auditors = id(new DiffusionCommitQuery())
      ->setViewer($this->getActor())
      ->needAuditRequests(true)
      ->withPHIDs(array($commit->getPHID()))
      ->executeOne();
    if (!$with_auditors) {
      throw new Exception(
        pht(
          'Failed to reload commit ("%s").',
          $commit->getPHID()));
    }

    $commit->attachAudits($with_auditors->getAudits());
  }

}
