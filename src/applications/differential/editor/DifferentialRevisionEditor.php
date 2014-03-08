<?php

/**
 * Handle major edit operations to DifferentialRevision -- adding and removing
 * reviewers, diffs, and CCs. Unlike simple edits, these changes trigger
 * complicated email workflows.
 */
final class DifferentialRevisionEditor extends PhabricatorEditor {

  protected $revision;

  protected $cc         = null;
  protected $reviewers  = null;
  protected $diff;
  protected $comments;
  protected $silentUpdate;

  private $auxiliaryFields = array();
  private $contentSource;
  private $isCreate;
  private $aphrontRequestForEventDispatch;


  public function setAphrontRequestForEventDispatch(AphrontRequest $request) {
    $this->aphrontRequestForEventDispatch = $request;
    return $this;
  }

  public function getAphrontRequestForEventDispatch() {
    return $this->aphrontRequestForEventDispatch;
  }

  public function __construct(DifferentialRevision $revision) {
    $this->revision = $revision;
    $this->isCreate = !($revision->getID());
  }

  public static function newRevisionFromConduitWithDiff(
    array $fields,
    DifferentialDiff $diff,
    PhabricatorUser $actor) {

    $revision = DifferentialRevision::initializeNewRevision($actor);
    $revision->setPHID($revision->generatePHID());

    $editor = new DifferentialRevisionEditor($revision);
    $editor->setActor($actor);
    $editor->addDiff($diff, null);
    $editor->copyFieldsFromConduit($fields);

    $editor->save();

    return $revision;
  }

  public function copyFieldsFromConduit(array $fields) {

    $actor = $this->getActor();
    $revision = $this->revision;
    $revision->loadRelationships();

    $all_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();

    $aux_fields = array();
    foreach ($all_fields as $aux_field) {
      $aux_field->setRevision($revision);
      $aux_field->setDiff($this->diff);
      $aux_field->setUser($actor);
      if ($aux_field->shouldAppearOnCommitMessage()) {
        $aux_fields[$aux_field->getCommitMessageKey()] = $aux_field;
      }
    }

    foreach ($fields as $field => $value) {
      if (empty($aux_fields[$field])) {
        throw new Exception(
          "Parsed commit message contains unrecognized field '{$field}'.");
      }
      $aux_fields[$field]->setValueFromParsedCommitMessage($value);
    }

    foreach ($aux_fields as $aux_field) {
      $aux_field->validateField();
    }

    $this->setAuxiliaryFields($all_fields);
  }

  public function setAuxiliaryFields(array $auxiliary_fields) {
    assert_instances_of($auxiliary_fields, 'DifferentialFieldSpecification');
    $this->auxiliaryFields = $auxiliary_fields;
    return $this;
  }

  public function getRevision() {
    return $this->revision;
  }

  public function setReviewers(array $reviewers) {
    $this->reviewers = $reviewers;
    return $this;
  }

  public function setCCPHIDs(array $cc) {
    $this->cc = $cc;
    return $this;
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function addDiff(DifferentialDiff $diff, $comments) {
    if ($diff->getRevisionID() &&
        $diff->getRevisionID() != $this->getRevision()->getID()) {
      $diff_id = (int)$diff->getID();
      $targ_id = (int)$this->getRevision()->getID();
      $real_id = (int)$diff->getRevisionID();
      throw new Exception(
        "Can not attach diff #{$diff_id} to Revision D{$targ_id}, it is ".
        "already attached to D{$real_id}.");
    }
    $this->diff = $diff;
    $this->comments = $comments;

    $repository = id(new DifferentialRepositoryLookup())
      ->setViewer($this->getActor())
      ->setDiff($diff)
      ->lookupRepository();

    if ($repository) {
      $this->getRevision()->setRepositoryPHID($repository->getPHID());
    }

    return $this;
  }

  protected function getDiff() {
    return $this->diff;
  }

  protected function getComments() {
    return $this->comments;
  }

  protected function getActorPHID() {
    return $this->getActor()->getPHID();
  }

  public function isNewRevision() {
    return !$this->getRevision()->getID();
  }


  public function save() {
    $revision = $this->getRevision();

    $is_new = $this->isNewRevision();

    $revision->loadRelationships();

    $this->willWriteRevision();

    if ($this->reviewers === null) {
      $this->reviewers = $revision->getReviewers();
    }

    if ($this->cc === null) {
      $this->cc = $revision->getCCPHIDs();
    }

    if ($is_new) {
      $content_blocks = array();
      foreach ($this->auxiliaryFields as $field) {
        if ($field->shouldExtractMentions()) {
          $content_blocks[] = $field->renderValueForCommitMessage(false);
        }
      }
      $phids = PhabricatorMarkupEngine::extractPHIDsFromMentions(
        $content_blocks);
      $this->cc = array_unique(array_merge($this->cc, $phids));
    }

    $diff = $this->getDiff();
    if ($diff) {
      $revision->setLineCount($diff->getLineCount());
    }

    // Save the revision, to generate its ID and PHID if it is new. We need
    // the ID/PHID in order to record them in Herald transcripts, but don't
    // want to hold a transaction open while running Herald because it is
    // potentially somewhat slow. The downside is that we may end up with a
    // saved revision/diff pair without appropriate CCs. We could be better
    // about this -- for example:
    //
    //  - Herald can't affect reviewers, so we could compute them before
    //    opening the transaction and then save them in the transaction.
    //  - Herald doesn't *really* need PHIDs to compute its effects, we could
    //    run it before saving these objects and then hand over the PHIDs later.
    //
    // But this should address the problem of orphaned revisions, which is
    // currently the only problem we experience in practice.

    $revision->openTransaction();

      if ($diff) {
        $revision->setBranchName($diff->getBranch());
        $revision->setArcanistProjectPHID($diff->getArcanistProjectPHID());
      }

      $revision->save();

      if ($diff) {
        $diff->setRevisionID($revision->getID());
        $diff->save();
      }

    $revision->saveTransaction();


    // We're going to build up three dictionaries: $add, $rem, and $stable. The
    // $add dictionary has added reviewers/CCs. The $rem dictionary has
    // reviewers/CCs who have been removed, and the $stable array is
    // reviewers/CCs who haven't changed. We're going to send new reviewers/CCs
    // a different ("welcome") email than we send stable reviewers/CCs.

    $old = array(
      'rev' => array_fill_keys($revision->getReviewers(), true),
      'ccs' => array_fill_keys($revision->getCCPHIDs(), true),
    );

    $xscript_header = null;
    $xscript_uri = null;

    $new = array(
      'rev' => array_fill_keys($this->reviewers, true),
      'ccs' => array_fill_keys($this->cc, true),
    );

    $rem_ccs = array();
    $xscript_phid = null;
    if ($diff) {
      $unsubscribed_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $revision->getPHID(),
        PhabricatorEdgeConfig::TYPE_OBJECT_HAS_UNSUBSCRIBER);

      $adapter = HeraldDifferentialRevisionAdapter::newLegacyAdapter(
        $revision,
        $diff);
      $adapter->setExplicitCCs($new['ccs']);
      $adapter->setExplicitReviewers($new['rev']);
      $adapter->setForbiddenCCs($unsubscribed_phids);
      $adapter->setIsNewObject($is_new);

      $xscript = HeraldEngine::loadAndApplyRules($adapter);
      $xscript_uri = '/herald/transcript/'.$xscript->getID().'/';
      $xscript_phid = $xscript->getPHID();
      $xscript_header = $xscript->getXHeraldRulesHeader();

      $xscript_header = HeraldTranscript::saveXHeraldRulesHeader(
        $revision->getPHID(),
        $xscript_header);

      $sub = array(
        'rev' => $adapter->getReviewersAddedByHerald(),
        'ccs' => $adapter->getCCsAddedByHerald(),
      );
      $rem_ccs = $adapter->getCCsRemovedByHerald();
      $blocking_reviewers = array_keys(
        $adapter->getBlockingReviewersAddedByHerald());

      HarbormasterBuildable::applyBuildPlans(
        $diff->getPHID(),
        $revision->getPHID(),
        $adapter->getBuildPlans());
    } else {
      $sub = array(
        'rev' => array(),
        'ccs' => array(),
      );
      $blocking_reviewers = array();
    }

    // Remove any CCs which are prevented by Herald rules.
    $sub['ccs'] = array_diff_key($sub['ccs'], $rem_ccs);
    $new['ccs'] = array_diff_key($new['ccs'], $rem_ccs);

    $add = array();
    $rem = array();
    $stable = array();
    foreach (array('rev', 'ccs') as $key) {
      $add[$key] = array();
      if ($new[$key] !== null) {
        $add[$key] += array_diff_key($new[$key], $old[$key]);
      }
      $add[$key] += array_diff_key($sub[$key], $old[$key]);

      $combined = $sub[$key];
      if ($new[$key] !== null) {
        $combined += $new[$key];
      }
      $rem[$key] = array_diff_key($old[$key], $combined);

      $stable[$key] = array_diff_key($old[$key], $add[$key] + $rem[$key]);
    }

    // Prevent Herald rules from adding a revision's owner as a reviewer.
    unset($add['rev'][$revision->getAuthorPHID()]);

    self::updateReviewers(
      $revision,
      $this->getActor(),
      array_keys($add['rev']),
      array_keys($rem['rev']),
      $blocking_reviewers);

    // We want to attribute new CCs to a "reasonPHID", representing the reason
    // they were added. This is either a user (if some user explicitly CCs
    // them, or uses "Add CCs...") or a Herald transcript PHID, indicating that
    // they were added by a Herald rule.

    if ($add['ccs'] || $rem['ccs']) {
      $reasons = array();
      foreach ($add['ccs'] as $phid => $ignored) {
        if (empty($new['ccs'][$phid])) {
          $reasons[$phid] = $xscript_phid;
        } else {
          $reasons[$phid] = $this->getActorPHID();
        }
      }
      foreach ($rem['ccs'] as $phid => $ignored) {
        if (empty($new['ccs'][$phid])) {
          $reasons[$phid] = $this->getActorPHID();
        } else {
          $reasons[$phid] = $xscript_phid;
        }
      }
    } else {
      $reasons = $this->getActorPHID();
    }

    self::alterCCs(
      $revision,
      $this->cc,
      array_keys($rem['ccs']),
      array_keys($add['ccs']),
      $reasons);

    $this->updateAuxiliaryFields();

    // Add the author and users included from Herald rules to the relevant set
    // of users so they get a copy of the email.
    if (!$this->silentUpdate) {
      if ($is_new) {
        $add['rev'][$this->getActorPHID()] = true;
        if ($diff) {
          $add['rev'] += $adapter->getEmailPHIDsAddedByHerald();
        }
      } else {
        $stable['rev'][$this->getActorPHID()] = true;
        if ($diff) {
          $stable['rev'] += $adapter->getEmailPHIDsAddedByHerald();
        }
      }
    }

    $mail = array();

    $phids = array($this->getActorPHID());

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getActor())
      ->withPHIDs($phids)
      ->execute();
    $actor_handle = $handles[$this->getActorPHID()];

    $changesets = null;
    $old_status = $revision->getStatus();

    if ($diff) {
      $changesets = $diff->loadChangesets();
      // TODO: This should probably be in DifferentialFeedbackEditor?
      if (!$is_new) {
        $this->createComment();
        $mail[] = id(new DifferentialNewDiffMail(
            $revision,
            $actor_handle,
            $changesets))
          ->setActor($this->getActor())
          ->setIsFirstMailAboutRevision(false)
          ->setIsFirstMailToRecipients(false)
          ->setCommentText($this->getComments())
          ->setToPHIDs(array_keys($stable['rev']))
          ->setCCPHIDs(array_keys($stable['ccs']));
      }

      // Save the changes we made above.

      $diff->setDescription(preg_replace('/\n.*/s', '', $this->getComments()));
      $diff->save();

      $this->updateAffectedPathTable($revision, $diff, $changesets);
      $this->updateRevisionHashTable($revision, $diff);

      // An updated diff should require review, as long as it's not closed
      // or accepted. The "accepted" status is "sticky" to encourage courtesy
      // re-diffs after someone accepts with minor changes/suggestions.

      $status = $revision->getStatus();
      if ($status != ArcanistDifferentialRevisionStatus::CLOSED &&
          $status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
        $revision->setStatus(ArcanistDifferentialRevisionStatus::NEEDS_REVIEW);
      }

    } else {
      $diff = $revision->loadActiveDiff();
      if ($diff) {
        $changesets = $diff->loadChangesets();
      } else {
        $changesets = array();
      }
    }

    $revision->save();

    // If the actor just deleted all the blocking/rejected reviewers, we may
    // be able to put the revision into "accepted".
    switch ($revision->getStatus()) {
      case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
      case ArcanistDifferentialRevisionStatus::CHANGES_PLANNED:
      case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
        $revision = self::updateAcceptedStatus(
          $this->getActor(),
          $revision);
        break;
    }

    $this->didWriteRevision();

    $event_data = array(
      'revision_id'          => $revision->getID(),
      'revision_phid'        => $revision->getPHID(),
      'revision_name'        => $revision->getTitle(),
      'revision_author_phid' => $revision->getAuthorPHID(),
      'action'               => $is_new
        ? DifferentialAction::ACTION_CREATE
        : DifferentialAction::ACTION_UPDATE,
      'feedback_content'     => $is_new
        ? phutil_utf8_shorten($revision->getSummary(), 140)
        : $this->getComments(),
      'actor_phid'           => $revision->getAuthorPHID(),
    );

    $mailed_phids = array();
    if (!$this->silentUpdate) {
      $revision->loadRelationships();

      if ($add['rev']) {
        $message = id(new DifferentialNewDiffMail(
            $revision,
            $actor_handle,
            $changesets))
          ->setActor($this->getActor())
          ->setIsFirstMailAboutRevision($is_new)
          ->setIsFirstMailToRecipients(true)
          ->setToPHIDs(array_keys($add['rev']));

        if ($is_new) {
          // The first time we send an email about a revision, put the CCs in
          // the "CC:" field of the same "Review Requested" email that reviewers
          // get, so you don't get two initial emails if you're on a list that
          // is CC'd.
          $message->setCCPHIDs(array_keys($add['ccs']));
        }

        $mail[] = $message;
      }

      // If we added CCs, we want to send them an email, but only if they were
      // not already a reviewer and were not added as one (in these cases, they
      // got a "NewDiff" mail, either in the past or just a moment ago). You can
      // still get two emails, but only if a revision is updated and you are
      // added as a reviewer at the same time a list you are on is added as a
      // CC, which is rare and reasonable.

      $implied_ccs = self::getImpliedCCs($revision);
      $implied_ccs = array_fill_keys($implied_ccs, true);
      $add['ccs'] = array_diff_key($add['ccs'], $implied_ccs);

      if (!$is_new && $add['ccs']) {
        $mail[] = id(new DifferentialCCWelcomeMail(
            $revision,
            $actor_handle,
            $changesets))
          ->setActor($this->getActor())
          ->setIsFirstMailToRecipients(true)
          ->setToPHIDs(array_keys($add['ccs']));
      }

      foreach ($mail as $message) {
        $message->setHeraldTranscriptURI($xscript_uri);
        $message->setXHeraldRulesHeader($xscript_header);
        $message->send();

        $mailed_phids[] = $message->getRawMail()->buildRecipientList();
      }
      $mailed_phids = array_mergev($mailed_phids);
    }

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType('PhabricatorFeedStoryDifferential')
      ->setStoryData($event_data)
      ->setStoryTime(time())
      ->setStoryAuthorPHID($revision->getAuthorPHID())
      ->setRelatedPHIDs(
        array(
          $revision->getPHID(),
          $revision->getAuthorPHID(),
        ))
      ->setPrimaryObjectPHID($revision->getPHID())
      ->setSubscribedPHIDs(
        array_merge(
          array($revision->getAuthorPHID()),
          $revision->getReviewers(),
          $revision->getCCPHIDs()))
      ->setMailRecipientPHIDs($mailed_phids)
      ->publish();

    id(new PhabricatorSearchIndexer())
      ->queueDocumentForIndexing($revision->getPHID());
  }

  protected static function alterCCs(
    DifferentialRevision $revision,
    array $stable_phids,
    array $rem_phids,
    array $add_phids,
    $reason_phid) {

    $dont_add = self::getImpliedCCs($revision);
    $add_phids = array_diff($add_phids, $dont_add);

    id(new PhabricatorSubscriptionsEditor())
      ->setActor(PhabricatorUser::getOmnipotentUser())
      ->setObject($revision)
      ->subscribeExplicit($add_phids)
      ->unsubscribe($rem_phids)
      ->save();
  }

  private static function getImpliedCCs(DifferentialRevision $revision) {
    return array_merge(
      $revision->getReviewers(),
      array($revision->getAuthorPHID()));
  }

  public static function updateReviewers(
    DifferentialRevision $revision,
    PhabricatorUser $actor,
    array $add_phids,
    array $remove_phids,
    array $blocking_phids = array()) {

    $reviewers = $revision->getReviewers();

    $editor = id(new PhabricatorEdgeEditor())
      ->setActor($actor);

    $reviewer_phids_map = array_fill_keys($reviewers, true);

    $blocking_phids = array_fuse($blocking_phids);
    foreach ($add_phids as $phid) {

      // Adding an already existing edge again would have cause memory loss
      // That is, the previous state for that reviewer would be lost
      if (isset($reviewer_phids_map[$phid])) {
        // TODO: If we're writing a blocking edge, we should overwrite an
        // existing weaker edge (like "added" or "commented"), just not a
        // stronger existing edge.
        continue;
      }

      if (isset($blocking_phids[$phid])) {
        $status = DifferentialReviewerStatus::STATUS_BLOCKING;
      } else {
        $status = DifferentialReviewerStatus::STATUS_ADDED;
      }

      $options = array(
        'data' => array(
          'status' => $status,
        )
      );

      $editor->addEdge(
        $revision->getPHID(),
        PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER,
        $phid,
        $options);
    }

    foreach ($remove_phids as $phid) {
      $editor->removeEdge(
        $revision->getPHID(),
        PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER,
        $phid);
    }

    $editor->save();
  }

  private function createComment() {
    $template = id(new DifferentialComment())
      ->setAuthorPHID($this->getActorPHID())
      ->setRevision($this->revision);

    if ($this->contentSource) {
      $content_source = $this->contentSource;
    } else {
      $content_source = PhabricatorContentSource::newForSource(
        PhabricatorContentSource::SOURCE_LEGACY,
        array());
    }

    $template->setContentSource($content_source);


    // Write the "update active diff" transaction.
    id(clone $template)
      ->setAction(DifferentialAction::ACTION_UPDATE)
      ->setMetadata(
        array(
          DifferentialComment::METADATA_DIFF_ID => $this->getDiff()->getID(),
        ))
      ->save();

    // If we have a comment, write the "add a comment" transaction.
    if (strlen($this->getComments())) {
      id(clone $template)
        ->setAction(DifferentialAction::ACTION_COMMENT)
        ->setContent($this->getComments())
        ->save();
    }
  }

  private function updateAuxiliaryFields() {
    $aux_map = array();
    foreach ($this->auxiliaryFields as $aux_field) {
      $key = $aux_field->getStorageKey();
      if ($key !== null) {
        $val = $aux_field->getValueForStorage();
        $aux_map[$key] = $val;
      }
    }

    if (!$aux_map) {
      return;
    }

    $revision = $this->revision;

    $fields = id(new DifferentialCustomFieldStorage())->loadAllWhere(
      'objectPHID = %s',
      $revision->getPHID());
    $fields = mpull($fields, null, 'getFieldIndex');

    foreach ($aux_map as $key => $val) {
      $index = PhabricatorHash::digestForIndex($key);
      $obj = idx($fields, $index);
      if (!strlen($val)) {
        // If the new value is empty, just delete the old row if one exists and
        // don't add a new row if it doesn't.
        if ($obj) {
          $obj->delete();
        }
      } else {
        if (!$obj) {
          $obj = new DifferentialCustomFieldStorage();
          $obj->setObjectPHID($revision->getPHID());
          $obj->setFieldIndex($index);
        }

        if ($obj->getFieldValue() !== $val) {
          $obj->setFieldValue($val);
          $obj->save();
        }
      }
    }
  }

  private function willWriteRevision() {
    foreach ($this->auxiliaryFields as $aux_field) {
      $aux_field->willWriteRevision($this);
    }

    $this->dispatchEvent(
      PhabricatorEventType::TYPE_DIFFERENTIAL_WILLEDITREVISION);
  }

  private function didWriteRevision() {
    foreach ($this->auxiliaryFields as $aux_field) {
      $aux_field->didWriteRevision($this);
    }

    $this->dispatchEvent(
      PhabricatorEventType::TYPE_DIFFERENTIAL_DIDEDITREVISION);
  }

  private function dispatchEvent($type) {
    $event = new PhabricatorEvent(
      $type,
      array(
        'revision'      => $this->revision,
        'new'           => $this->isCreate,
      ));

    $event->setUser($this->getActor());

    $request = $this->getAphrontRequestForEventDispatch();
    if ($request) {
      $event->setAphrontRequest($request);
    }

    PhutilEventEngine::dispatchEvent($event);
  }

  /**
   * Update the table which links Differential revisions to paths they affect,
   * so Diffusion can efficiently find pending revisions for a given file.
   */
  private function updateAffectedPathTable(
    DifferentialRevision $revision,
    DifferentialDiff $diff,
    array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');

    $project = $diff->loadArcanistProject();
    if (!$project) {
      // Probably an old revision from before projects.
      return;
    }

    $repository = $project->loadRepository();
    if (!$repository) {
      // Probably no project <-> repository link, or the repository where the
      // project lives is untracked.
      return;
    }

    $path_prefix = null;

    $local_root = $diff->getSourceControlPath();
    if ($local_root) {
      // We're in a working copy which supports subdirectory checkouts (e.g.,
      // SVN) so we need to figure out what prefix we should add to each path
      // (e.g., trunk/projects/example/) to get the absolute path from the
      // root of the repository. DVCS systems like Git and Mercurial are not
      // affected.

      // Normalize both paths and check if the repository root is a prefix of
      // the local root. If so, throw it away. Note that this correctly handles
      // the case where the remote path is "/".
      $local_root = id(new PhutilURI($local_root))->getPath();
      $local_root = rtrim($local_root, '/');

      $repo_root = id(new PhutilURI($repository->getRemoteURI()))->getPath();
      $repo_root = rtrim($repo_root, '/');

      if (!strncmp($repo_root, $local_root, strlen($repo_root))) {
        $path_prefix = substr($local_root, strlen($repo_root));
      }
    }

    $paths = array();
    foreach ($changesets as $changeset) {
      $paths[] = $path_prefix.'/'.$changeset->getFilename();
    }

    // Mark this as also touching all parent paths, so you can see all pending
    // changes to any file within a directory.
    $all_paths = array();
    foreach ($paths as $local) {
      foreach (DiffusionPathIDQuery::expandPathToRoot($local) as $path) {
        $all_paths[$path] = true;
      }
    }
    $all_paths = array_keys($all_paths);

    $path_ids =
      PhabricatorRepositoryCommitChangeParserWorker::lookupOrCreatePaths(
        $all_paths);

    $table = new DifferentialAffectedPath();
    $conn_w = $table->establishConnection('w');

    $sql = array();
    foreach ($path_ids as $path_id) {
      $sql[] = qsprintf(
        $conn_w,
        '(%d, %d, %d, %d)',
        $repository->getID(),
        $path_id,
        time(),
        $revision->getID());
    }

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE revisionID = %d',
      $table->getTableName(),
      $revision->getID());
    foreach (array_chunk($sql, 256) as $chunk) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (repositoryID, pathID, epoch, revisionID) VALUES %Q',
        $table->getTableName(),
        implode(', ', $chunk));
    }
  }


  /**
   * Update the table connecting revisions to DVCS local hashes, so we can
   * identify revisions by commit/tree hashes.
   */
  private function updateRevisionHashTable(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {

    $vcs = $diff->getSourceControlSystem();
    if ($vcs == DifferentialRevisionControlSystem::SVN) {
      // Subversion has no local commit or tree hash information, so we don't
      // have to do anything.
      return;
    }

    $property = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $diff->getID(),
      'local:commits');
    if (!$property) {
      return;
    }

    $hashes = array();

    $data = $property->getData();
    switch ($vcs) {
      case DifferentialRevisionControlSystem::GIT:
        foreach ($data as $commit) {
          $hashes[] = array(
            ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT,
            $commit['commit'],
          );
          $hashes[] = array(
            ArcanistDifferentialRevisionHash::HASH_GIT_TREE,
            $commit['tree'],
          );
        }
        break;
      case DifferentialRevisionControlSystem::MERCURIAL:
        foreach ($data as $commit) {
          $hashes[] = array(
            ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT,
            $commit['rev'],
          );
        }
        break;
    }

    $conn_w = $revision->establishConnection('w');

    $sql = array();
    foreach ($hashes as $info) {
      list($type, $hash) = $info;
      $sql[] = qsprintf(
        $conn_w,
        '(%d, %s, %s)',
        $revision->getID(),
        $type,
        $hash);
    }

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE revisionID = %d',
      ArcanistDifferentialRevisionHash::TABLE_NAME,
      $revision->getID());

    if ($sql) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (revisionID, type, hash) VALUES %Q',
        ArcanistDifferentialRevisionHash::TABLE_NAME,
        implode(', ', $sql));
    }
  }

  /**
   * Try to move a revision to "accepted". We look for:
   *
   *   - at least one accepting reviewer who is a user; and
   *   - no rejects; and
   *   - no blocking reviewers.
   */
  public static function updateAcceptedStatus(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($revision->getID()))
      ->needRelationships(true)
      ->needReviewerStatus(true)
      ->needReviewerAuthority(true)
      ->executeOne();

    $has_user_accept = false;
    foreach ($revision->getReviewerStatus() as $reviewer) {
      $status = $reviewer->getStatus();
      if ($status == DifferentialReviewerStatus::STATUS_BLOCKING) {
        // We have a blocking reviewer, so just leave the revision in its
        // existing state.
        return $revision;
      }

      if ($status == DifferentialReviewerStatus::STATUS_REJECTED) {
        // We have a rejecting reviewer, so leave the revisoin as is.
        return $revision;
      }

      if ($reviewer->isUser()) {
        if ($status == DifferentialReviewerStatus::STATUS_ACCEPTED) {
          $has_user_accept = true;
        }
      }
    }

    if ($has_user_accept) {
      $revision
        ->setStatus(ArcanistDifferentialRevisionStatus::ACCEPTED)
        ->save();
    }

    return $revision;
  }

}
