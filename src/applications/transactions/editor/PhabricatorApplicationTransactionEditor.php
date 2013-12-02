<?php

/**
 * @task mail   Sending Mail
 * @task feed   Publishing Feed Stories
 * @task search Search Index
 * @task files  Integration with Files
 */
abstract class PhabricatorApplicationTransactionEditor
  extends PhabricatorEditor {

  private $contentSource;
  private $object;
  private $xactions;

  private $isNewObject;
  private $mentionedPHIDs;
  private $continueOnNoEffect;
  private $continueOnMissingFields;
  private $parentMessageID;
  private $heraldAdapter;
  private $heraldTranscript;
  private $subscribers;

  private $isPreview;

  /**
   * When the editor tries to apply transactions that have no effect, should
   * it raise an exception (default) or drop them and continue?
   *
   * Generally, you will set this flag for edits coming from "Edit" interfaces,
   * and leave it cleared for edits coming from "Comment" interfaces, so the
   * user will get a useful error if they try to submit a comment that does
   * nothing (e.g., empty comment with a status change that has already been
   * performed by another user).
   *
   * @param bool  True to drop transactions without effect and continue.
   * @return this
   */
  public function setContinueOnNoEffect($continue) {
    $this->continueOnNoEffect = $continue;
    return $this;
  }

  public function getContinueOnNoEffect() {
    return $this->continueOnNoEffect;
  }


  /**
   * When the editor tries to apply transactions which don't populate all of
   * an object's required fields, should it raise an exception (default) or
   * drop them and continue?
   *
   * For example, if a user adds a new required custom field (like "Severity")
   * to a task, all existing tasks won't have it populated. When users
   * manually edit existing tasks, it's usually desirable to have them provide
   * a severity. However, other operations (like batch editing just the
   * owner of a task) will fail by default.
   *
   * By setting this flag for edit operations which apply to specific fields
   * (like the priority, batch, and merge editors in Maniphest), these
   * operations can continue to function even if an object is outdated.
   *
   * @param bool  True to continue when transactions don't completely satisfy
   *              all required fields.
   * @return this
   */
  public function setContinueOnMissingFields($continue_on_missing_fields) {
    $this->continueOnMissingFields = $continue_on_missing_fields;
    return $this;
  }

  public function getContinueOnMissingFields() {
    return $this->continueOnMissingFields;
  }


  /**
   * Not strictly necessary, but reply handlers ideally set this value to
   * make email threading work better.
   */
  public function setParentMessageID($parent_message_id) {
    $this->parentMessageID = $parent_message_id;
    return $this;
  }
  public function getParentMessageID() {
    return $this->parentMessageID;
  }

  protected function getIsNewObject() {
    return $this->isNewObject;
  }

  protected function getMentionedPHIDs() {
    return $this->mentionedPHIDs;
  }

  public function setIsPreview($is_preview) {
    $this->isPreview = $is_preview;
    return $this;
  }

  public function getIsPreview() {
    return $this->isPreview;
  }

  public function getTransactionTypes() {
    $types = array();

    if ($this->object instanceof PhabricatorSubscribableInterface) {
      $types[] = PhabricatorTransactions::TYPE_SUBSCRIBERS;
    }

    if ($this->object instanceof PhabricatorCustomFieldInterface) {
      $types[] = PhabricatorTransactions::TYPE_CUSTOMFIELD;
    }

    return $types;
  }

  private function adjustTransactionValues(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    $old = $this->getTransactionOldValue($object, $xaction);
    $xaction->setOldValue($old);

    $new = $this->getTransactionNewValue($object, $xaction);
    $xaction->setNewValue($new);
  }

  private function getTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return array_values($this->subscribers);
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return $object->getViewPolicy();
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return $object->getEditPolicy();
      case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $xaction->getMetadataValue('edge:type');
        if (!$edge_type) {
          throw new Exception("Edge transaction has no 'edge:type'!");
        }

        $old_edges = array();
        if ($object->getPHID()) {
          $edge_src = $object->getPHID();

          $old_edges = id(new PhabricatorEdgeQuery())
            ->withSourcePHIDs(array($edge_src))
            ->withEdgeTypes(array($edge_type))
            ->needEdgeData(true)
            ->execute();

          $old_edges = $old_edges[$edge_src][$edge_type];
        }
        return $old_edges;
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        // NOTE: Custom fields have their old value pre-populated when they are
        // built by PhabricatorCustomFieldList.
        return $xaction->getOldValue();
      default:
        return $this->getCustomTransactionOldValue($object, $xaction);
    }
  }

  private function getTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return $this->getPHIDTransactionNewValue($xaction);
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return $xaction->getNewValue();
      case PhabricatorTransactions::TYPE_EDGE:
        return $this->getEdgeTransactionNewValue($xaction);
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->getNewValueFromApplicationTransactions($xaction);
      default:
        return $this->getCustomTransactionNewValue($object, $xaction);
    }
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception("Capability not supported!");
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception("Capability not supported!");
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return $xaction->hasComment();
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->getApplicationTransactionHasEffect($xaction);
    }

    return ($xaction->getOldValue() !== $xaction->getNewValue());
  }

  protected function shouldApplyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  protected function applyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {
    throw new Exception('Not implemented.');
  }

  private function applyInternalEffects(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->applyApplicationTransactionInternalEffects($xaction);
    }
    return $this->applyCustomInternalTransaction($object, $xaction);
  }

  private function applyExternalEffects(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $subeditor = id(new PhabricatorSubscriptionsEditor())
          ->setObject($object)
          ->setActor($this->requireActor());

        $old_map = array_fuse($xaction->getOldValue());
        $new_map = array_fuse($xaction->getNewValue());

        $subeditor->unsubscribe(
          array_keys(
            array_diff_key($old_map, $new_map)));

        $subeditor->subscribeExplicit(
          array_keys(
            array_diff_key($new_map, $old_map)));

        $subeditor->save();

        // for the rest of these edits, subscribers should include those just
        // added as well as those just removed.
        $subscribers = array_unique(array_merge(
          $this->subscribers,
          $xaction->getOldValue(),
          $xaction->getNewValue()));
        $this->subscribers = $subscribers;

        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();
        $src = $object->getPHID();
        $type = $xaction->getMetadataValue('edge:type');

        foreach ($new as $dst_phid => $edge) {
          $new[$dst_phid]['src'] = $src;
        }

        $editor = id(new PhabricatorEdgeEditor())
          ->setActor($this->getActor());

        foreach ($old as $dst_phid => $edge) {
          if (!empty($new[$dst_phid])) {
            if ($old[$dst_phid]['data'] === $new[$dst_phid]['data']) {
              continue;
            }
          }
          $editor->removeEdge($src, $type, $dst_phid);
        }

        foreach ($new as $dst_phid => $edge) {
          if (!empty($old[$dst_phid])) {
            if ($old[$dst_phid]['data'] === $new[$dst_phid]['data']) {
              continue;
            }
          }

          $data = array(
            'data' => $edge['data'],
          );

          $editor->addEdge($src, $type, $dst_phid, $data);
        }

        $editor->save();
        break;
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->applyApplicationTransactionExternalEffects($xaction);
    }

    return $this->applyCustomExternalTransaction($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    $type = $xaction->getTransactionType();
    throw new Exception(
      "Transaction type '{$type}' is missing an internal apply ".
      "implementation!");
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    $type = $xaction->getTransactionType();
    throw new Exception(
      "Transaction type '{$type}' is missing an external apply ".
      "implementation!");
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function setContentSourceFromRequest(AphrontRequest $request) {
    return $this->setContentSource(
      PhabricatorContentSource::newFromRequest($request));
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  final public function applyTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $this->object = $object;
    $this->xactions = $xactions;
    $this->isNewObject = ($object->getPHID() === null);

    $this->validateEditParameters($object, $xactions);

    $actor = $this->requireActor();

    $this->loadSubscribers($object);

    $xactions = $this->applyImplicitCC($object, $xactions);

    $mention_xaction = $this->buildMentionTransaction($object, $xactions);
    if ($mention_xaction) {
      $xactions[] = $mention_xaction;
    }

    $xactions = $this->combineTransactions($xactions);

    foreach ($xactions as $xaction) {
      // TODO: This needs to be more sophisticated once we have meta-policies.
      $xaction->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC);
      $xaction->setEditPolicy($actor->getPHID());

      $xaction->setAuthorPHID($actor->getPHID());
      $xaction->setContentSource($this->getContentSource());
      $xaction->attachViewer($this->getActor());
    }

    $is_preview = $this->getIsPreview();
    $read_locking = false;
    $transaction_open = false;

    if (!$is_preview) {
      $errors = array();
      $type_map = mgroup($xactions, 'getTransactionType');
      foreach ($this->getTransactionTypes() as $type) {
        $type_xactions = idx($type_map, $type, array());
        $errors[] = $this->validateTransaction($object, $type, $type_xactions);
      }

      $errors = array_mergev($errors);

      $continue_on_missing = $this->getContinueOnMissingFields();
      foreach ($errors as $key => $error) {
        if ($continue_on_missing && $error->getIsMissingFieldError()) {
          unset($errors[$key]);
        }
      }

      if ($errors) {
        throw new PhabricatorApplicationTransactionValidationException($errors);
      }

      $file_phids = $this->extractFilePHIDs($object, $xactions);

      if ($object->getID()) {
        foreach ($xactions as $xaction) {

          // If any of the transactions require a read lock, hold one and
          // reload the object. We need to do this fairly early so that the
          // call to `adjustTransactionValues()` (which populates old values)
          // is based on the synchronized state of the object, which may differ
          // from the state when it was originally loaded.

          if ($this->shouldReadLock($object, $xaction)) {
            $object->openTransaction();
            $object->beginReadLocking();
            $transaction_open = true;
            $read_locking = true;
            $object->reload();
            break;
          }
        }
      }

      if ($this->shouldApplyInitialEffects($object, $xactions)) {
        if (!$transaction_open) {
          $object->openTransaction();
          $transaction_open = true;
        }
      }
    }

    if ($this->shouldApplyInitialEffects($object, $xactions)) {
      $this->applyInitialEffects($object, $xactions);
    }

    foreach ($xactions as $xaction) {
      $this->adjustTransactionValues($object, $xaction);
    }

    $xactions = $this->filterTransactions($object, $xactions);

    if (!$xactions) {
      if ($read_locking) {
        $object->endReadLocking();
        $read_locking = false;
      }
      if ($transaction_open) {
        $object->killTransaction();
        $transaction_open = false;
      }
      return array();
    }

    // Now that we've merged, filtered, and combined transactions, check for
    // required capabilities.
    foreach ($xactions as $xaction) {
      $this->requireCapabilities($object, $xaction);
    }

    $xactions = $this->sortTransactions($xactions);

    if ($is_preview) {
      $this->loadHandles($xactions);
      return $xactions;
    }

    $comment_editor = id(new PhabricatorApplicationTransactionCommentEditor())
      ->setActor($actor)
      ->setContentSource($this->getContentSource());

    if (!$transaction_open) {
      $object->openTransaction();
    }

      foreach ($xactions as $xaction) {
        $this->applyInternalEffects($object, $xaction);
      }

      $object->save();

      foreach ($xactions as $xaction) {
        $xaction->setObjectPHID($object->getPHID());
        if ($xaction->getComment()) {
          $xaction->setPHID($xaction->generatePHID());
          $comment_editor->applyEdit($xaction, $xaction->getComment());
        } else {
          $xaction->save();
        }
      }

      if ($file_phids) {
        $this->attachFiles($object, $file_phids);
      }

      foreach ($xactions as $xaction) {
        $this->applyExternalEffects($object, $xaction);
      }

      if ($this->supportsHerald()) {
        $this->applyHeraldRules($object, $xactions);
      }

      $this->applyFinalEffects($object, $xactions);

      if ($read_locking) {
        $object->endReadLocking();
        $read_locking = false;
      }

    $object->saveTransaction();

    $this->loadHandles($xactions);

    $mail = null;
    if ($this->shouldSendMail($object, $xactions)) {
      $mail = $this->sendMail($object, $xactions);
    }

    if ($this->supportsSearch()) {
      id(new PhabricatorSearchIndexer())
        ->indexDocumentByPHID($object->getPHID());
    }

    if ($this->supportsFeed()) {
      $mailed = array();
      if ($mail) {
        $mailed = $mail->buildRecipientList();
      }
      $this->publishFeedStory(
        $object,
        $xactions,
        $mailed);
    }

    $this->didApplyTransactions($xactions);

    if ($object instanceof PhabricatorCustomFieldInterface) {
      // Maybe this makes more sense to move into the search index itself? For
      // now I'm putting it here since I think we might end up with things that
      // need it to be up to date once the next page loads, but if we don't go
      // there we we could move it into search once search moves to the daemons.

      $fields = PhabricatorCustomField::getObjectFields(
        $object,
        PhabricatorCustomField::ROLE_APPLICATIONSEARCH);
      $fields->readFieldsFromStorage($object);
      $fields->rebuildIndexes($object);
    }

    return $xactions;
  }

  protected function didApplyTransactions(array $xactions) {
    // Hook for subclasses.
    return;
  }


  /**
   * Determine if the editor should hold a read lock on the object while
   * applying a transaction.
   *
   * If the editor does not hold a lock, two editors may read an object at the
   * same time, then apply their changes without any synchronization. For most
   * transactions, this does not matter much. However, it is important for some
   * transactions. For example, if an object has a transaction count on it, both
   * editors may read the object with `count = 23`, then independently update it
   * and save the object with `count = 24` twice. This will produce the wrong
   * state: the object really has 25 transactions, but the count is only 24.
   *
   * Generally, transactions fall into one of four buckets:
   *
   *   - Append operations: Actions like adding a comment to an object purely
   *     add information to its state, and do not depend on the current object
   *     state in any way. These transactions never need to hold locks.
   *   - Overwrite operations: Actions like changing the title or description
   *     of an object replace the current value with a new value, so the end
   *     state is consistent without a lock. We currently do not lock these
   *     transactions, although we may in the future.
   *   - Edge operations: Edge and subscription operations have internal
   *     synchronization which limits the damage race conditions can cause.
   *     We do not currently lock these transactions, although we may in the
   *     future.
   *   - Update operations: Actions like incrementing a count on an object.
   *     These operations generally should use locks, unless it is not
   *     important that the state remain consistent in the presence of races.
   *
   * @param   PhabricatorLiskDAO  Object being updated.
   * @param   PhabricatorApplicationTransaction Transaction being applied.
   * @return  bool                True to synchronize the edit with a lock.
   */
  protected function shouldReadLock(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return false;
  }

  private function loadHandles(array $xactions) {
    $phids = array();
    foreach ($xactions as $key => $xaction) {
      $phids[$key] = $xaction->getRequiredHandlePHIDs();
    }
    $handles = array();
    $merged = array_mergev($phids);
    if ($merged) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireActor())
        ->withPHIDs($merged)
        ->execute();
    }
    foreach ($xactions as $key => $xaction) {
      $xaction->setHandles(array_select_keys($handles, $phids[$key]));
    }
  }

  private function loadSubscribers(PhabricatorLiskDAO $object) {
    if ($object->getPHID() &&
        ($object instanceof PhabricatorSubscribableInterface)) {
      $subs = PhabricatorSubscribersQuery::loadSubscribersForPHID(
        $object->getPHID());
      $this->subscribers = array_fuse($subs);
    } else {
      $this->subscribers = array();
    }
  }

  private function validateEditParameters(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if (!$this->getContentSource()) {
      throw new Exception(
        "Call setContentSource() before applyTransactions()!");
    }

    // Do a bunch of sanity checks that the incoming transactions are fresh.
    // They should be unsaved and have only "transactionType" and "newValue"
    // set.

    $types = array_fill_keys($this->getTransactionTypes(), true);

    assert_instances_of($xactions, 'PhabricatorApplicationTransaction');
    foreach ($xactions as $xaction) {
      if ($xaction->getPHID() || $xaction->getID()) {
        throw new Exception(
          "You can not apply transactions which already have IDs/PHIDs!");
      }
      if ($xaction->getObjectPHID()) {
        throw new Exception(
          "You can not apply transactions which already have objectPHIDs!");
      }
      if ($xaction->getAuthorPHID()) {
        throw new Exception(
          "You can not apply transactions which already have authorPHIDs!");
      }
      if ($xaction->getCommentPHID()) {
        throw new Exception(
          "You can not apply transactions which already have commentPHIDs!");
      }
      if ($xaction->getCommentVersion() !== 0) {
        throw new Exception(
          "You can not apply transactions which already have commentVersions!");
      }

      $exempt_types = array(
        // CustomField logic currently prefills these before we enter the
        // transaction editor.
        PhabricatorTransactions::TYPE_CUSTOMFIELD => true,

        // TODO: Remove this, this edge type is encumbered with a bunch of
        // legacy nonsense.
        ManiphestTransaction::TYPE_EDGE => true,
      );

      if (empty($exempt_types[$xaction->getTransactionType()])) {
        if ($xaction->getOldValue() !== null) {
          throw new Exception(
            "You can not apply transactions which already have oldValue!");
        }
      }

      $type = $xaction->getTransactionType();
      if (empty($types[$type])) {
        throw new Exception("Transaction has unknown type '{$type}'.");
      }
    }

    // The actor must have permission to view and edit the object.

    $actor = $this->requireActor();

    PhabricatorPolicyFilter::requireCapability(
      $actor,
      $object,
      PhabricatorPolicyCapability::CAN_VIEW);
  }

  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        // You must have the edit capability to alter the edit policy of an
        // object. For other default transaction types, we don't enforce
        // anything for the moment.

        PhabricatorPolicyFilter::requireCapability(
          $this->requireActor(),
          $object,
          PhabricatorPolicyCapability::CAN_EDIT);
        break;
    }
  }

  private function buildMentionTransaction(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      return null;
    }

    $texts = array();
    foreach ($xactions as $xaction) {
      $texts[] = $this->getRemarkupBlocksFromTransaction($xaction);
    }
    $texts = array_mergev($texts);

    $phids = PhabricatorMarkupEngine::extractPHIDsFromMentions($texts);

    $this->mentionedPHIDs = $phids;

    if ($object->getPHID()) {
      // Don't try to subscribe already-subscribed mentions: we want to generate
      // a dialog about an action having no effect if the user explicitly adds
      // existing CCs, but not if they merely mention existing subscribers.
      $phids = array_diff($phids, $this->subscribers);
    }

    foreach ($phids as $key => $phid) {
      if ($object->isAutomaticallySubscribed($phid)) {
        unset($phids[$key]);
      }
    }
    $phids = array_values($phids);

    if (!$phids) {
      return null;
    }

    $xaction = newv(get_class(head($xactions)), array());
    $xaction->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS);
    $xaction->setNewValue(array('+' => $phids));

    return $xaction;
  }

  protected function getRemarkupBlocksFromTransaction(
    PhabricatorApplicationTransaction $transaction) {
    $texts = array();
    if ($transaction->getComment()) {
      $texts[] = $transaction->getComment()->getContent();
    }
    return $texts;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();

    switch ($type) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return $this->mergePHIDOrEdgeTransactions($u, $v);
      case PhabricatorTransactions::TYPE_EDGE:
        $u_type = $u->getMetadataValue('edge:type');
        $v_type = $v->getMetadataValue('edge:type');
        if ($u_type == $v_type) {
          return $this->mergePHIDOrEdgeTransactions($u, $v);
        }
        return null;
    }

    // By default, do not merge the transactions.
    return null;
  }


  /**
   * Attempt to combine similar transactions into a smaller number of total
   * transactions. For example, two transactions which edit the title of an
   * object can be merged into a single edit.
   */
  private function combineTransactions(array $xactions) {
    $stray_comments = array();

    $result = array();
    $types = array();
    foreach ($xactions as $key => $xaction) {
      $type = $xaction->getTransactionType();
      if (isset($types[$type])) {
        foreach ($types[$type] as $other_key) {
          $merged = $this->mergeTransactions($result[$other_key], $xaction);
          if ($merged) {
            $result[$other_key] = $merged;

            if ($xaction->getComment() &&
                ($xaction->getComment() !== $merged->getComment())) {
              $stray_comments[] = $xaction->getComment();
            }

            if ($result[$other_key]->getComment() &&
                ($result[$other_key]->getComment() !== $merged->getComment())) {
              $stray_comments[] = $result[$other_key]->getComment();
            }

            // Move on to the next transaction.
            continue 2;
          }
        }
      }
      $result[$key] = $xaction;
      $types[$type][] = $key;
    }

    // If we merged any comments away, restore them.
    foreach ($stray_comments as $comment) {
      $xaction = newv(get_class(head($result)), array());
      $xaction->setTransactionType(PhabricatorTransactions::TYPE_COMMENT);
      $xaction->setComment($comment);
      $result[] = $xaction;
    }

    return array_values($result);
  }

  protected function mergePHIDOrEdgeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $result = $u->getNewValue();
    foreach ($v->getNewValue() as $key => $value) {
      $result[$key] = array_merge($value, idx($result, $key, array()));
    }
    $u->setNewValue($result);

    return $u;
  }

  protected function getPHIDTransactionNewValue(
    PhabricatorApplicationTransaction $xaction) {

    $old = array_fuse($xaction->getOldValue());

    $new = $xaction->getNewValue();
    $new_add = idx($new, '+', array());
    unset($new['+']);
    $new_rem = idx($new, '-', array());
    unset($new['-']);
    $new_set = idx($new, '=', null);
    if ($new_set !== null) {
      $new_set = array_fuse($new_set);
    }
    unset($new['=']);

    if ($new) {
      throw new Exception(
        "Invalid 'new' value for PHID transaction. Value should contain only ".
        "keys '+' (add PHIDs), '-' (remove PHIDs) and '=' (set PHIDS).");
    }

    $result = array();

    foreach ($old as $phid) {
      if ($new_set !== null && empty($new_set[$phid])) {
        continue;
      }
      $result[$phid] = $phid;
    }

    if ($new_set !== null) {
      foreach ($new_set as $phid) {
        $result[$phid] = $phid;
      }
    }

    foreach ($new_add as $phid) {
      $result[$phid] = $phid;
    }

    foreach ($new_rem as $phid) {
      unset($result[$phid]);
    }

    return array_values($result);
  }

  protected function getEdgeTransactionNewValue(
    PhabricatorApplicationTransaction $xaction) {

    $new = $xaction->getNewValue();
    $new_add = idx($new, '+', array());
    unset($new['+']);
    $new_rem = idx($new, '-', array());
    unset($new['-']);
    $new_set = idx($new, '=', null);
    unset($new['=']);

    if ($new) {
      throw new Exception(
        "Invalid 'new' value for Edge transaction. Value should contain only ".
        "keys '+' (add edges), '-' (remove edges) and '=' (set edges).");
    }

    $old = $xaction->getOldValue();

    $lists = array($new_set, $new_add, $new_rem);
    foreach ($lists as $list) {
      $this->checkEdgeList($list);
    }

    $result = array();
    foreach ($old as $dst_phid => $edge) {
      if ($new_set !== null && empty($new_set[$dst_phid])) {
        continue;
      }
      $result[$dst_phid] = $this->normalizeEdgeTransactionValue(
        $xaction,
        $edge);
    }

    if ($new_set !== null) {
      foreach ($new_set as $dst_phid => $edge) {
        $result[$dst_phid] = $this->normalizeEdgeTransactionValue(
          $xaction,
          $edge);
      }
    }

    foreach ($new_add as $dst_phid => $edge) {
      $result[$dst_phid] = $this->normalizeEdgeTransactionValue(
        $xaction,
        $edge);
    }

    foreach ($new_rem as $dst_phid => $edge) {
      unset($result[$dst_phid]);
    }

    return $result;
  }

  private function checkEdgeList($list) {
    if (!$list) {
      return;
    }
    foreach ($list as $key => $item) {
      if (phid_get_type($key) === PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
        throw new Exception(
          "Edge transactions must have destination PHIDs as in edge ".
          "lists (found key '{$key}').");
      }
      if (!is_array($item) && $item !== $key) {
        throw new Exception(
          "Edge transactions must have PHIDs or edge specs as values ".
          "(found value '{$item}').");
      }
    }
  }

  protected function normalizeEdgeTransactionValue(
    PhabricatorApplicationTransaction $xaction,
    $edge) {

    if (!is_array($edge)) {
      $edge = array(
        'dst' => $edge,
      );
    }

    $edge_type = $xaction->getMetadataValue('edge:type');

    if (empty($edge['type'])) {
      $edge['type'] = $edge_type;
    } else {
      if ($edge['type'] != $edge_type) {
        $this_type = $edge['type'];
        throw new Exception(
          "Edge transaction includes edge of type '{$this_type}', but ".
          "transaction is of type '{$edge_type}'. Each edge transaction must ".
          "alter edges of only one type.");
      }
    }

    if (!isset($edge['data'])) {
      $edge['data'] = null;
    }

    return $edge;
  }

  protected function sortTransactions(array $xactions) {
    $head = array();
    $tail = array();

    // Move bare comments to the end, so the actions precede them.
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == PhabricatorTransactions::TYPE_COMMENT) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }


  protected function filterTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $type_comment = PhabricatorTransactions::TYPE_COMMENT;

    $no_effect = array();
    $has_comment = false;
    $any_effect = false;
    foreach ($xactions as $key => $xaction) {
      if ($this->transactionHasEffect($object, $xaction)) {
        if ($xaction->getTransactionType() != $type_comment) {
          $any_effect = true;
        }
      } else {
        $no_effect[$key] = $xaction;
      }
      if ($xaction->hasComment()) {
        $has_comment = true;
      }
    }

    if (!$no_effect) {
      return $xactions;
    }

    if (!$this->getContinueOnNoEffect() && !$this->getIsPreview()) {
      throw new PhabricatorApplicationTransactionNoEffectException(
        $no_effect,
        $any_effect,
        $has_comment);
    }

    if (!$any_effect && !$has_comment) {
      // If we only have empty comment transactions, just drop them all.
      return array();
    }

    foreach ($no_effect as $key => $xaction) {
      if ($xaction->getComment()) {
        $xaction->setTransactionType($type_comment);
        $xaction->setOldValue(null);
        $xaction->setNewValue(null);
      } else {
        unset($xactions[$key]);
      }
    }

    return $xactions;
  }


  /**
   * Hook for validating transactions. This callback will be invoked for each
   * available transaction type, even if an edit does not apply any transactions
   * of that type. This allows you to raise exceptions when required fields are
   * missing, by detecting that the object has no field value and there is no
   * transaction which sets one.
   *
   * @param PhabricatorLiskDAO Object being edited.
   * @param string Transaction type to validate.
   * @param list<PhabricatorApplicationTransaction> Transactions of given type,
   *   which may be empty if the edit does not apply any transactions of the
   *   given type.
   * @return list<PhabricatorApplicationTransactionValidationError> List of
   *   validation errors.
   */
  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = array();
    switch ($type) {
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $groups = array();
        foreach ($xactions as $xaction) {
          $groups[$xaction->getMetadataValue('customfield:key')][] = $xaction;
        }

        $field_list = PhabricatorCustomField::getObjectFields(
          $object,
          PhabricatorCustomField::ROLE_EDIT);

        $role_xactions = PhabricatorCustomField::ROLE_APPLICATIONTRANSACTIONS;
        foreach ($field_list->getFields() as $field) {
          if (!$field->shouldEnableForRole($role_xactions)) {
            continue;
          }
          $errors[] = $field->validateApplicationTransactions(
            $this,
            $type,
            idx($groups, $field->getFieldKey(), array()));
        }
        break;
    }

    return array_mergev($errors);
  }


  /**
   * Check for a missing text field.
   *
   * A text field is missing if the object has no value and there are no
   * transactions which set a value, or if the transactions remove the value.
   * This method is intended to make implementing @{method:validateTransaction}
   * more convenient:
   *
   *   $missing = $this->validateIsEmptyTextField(
   *     $object->getName(),
   *     $xactions);
   *
   * This will return `true` if the net effect of the object and transactions
   * is an empty field.
   *
   * @param wild Current field value.
   * @param list<PhabricatorApplicationTransaction> Transactions editing the
   *          field.
   * @return bool True if the field will be an empty text field after edits.
   */
  protected function validateIsEmptyTextField($field_value, array $xactions) {
    if (strlen($field_value) && empty($xactions)) {
      return false;
    }

    if ($xactions && strlen(last($xactions)->getNewValue())) {
      return false;
    }

    return true;
  }


/* -(  Implicit CCs  )------------------------------------------------------- */


  /**
   * When a user interacts with an object, we might want to add them to CC.
   */
  final public function applyImplicitCC(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      // If the object isn't subscribable, we can't CC them.
      return $xactions;
    }

    $actor_phid = $this->requireActor()->getPHID();
    if ($object->isAutomaticallySubscribed($actor_phid)) {
      // If they're auto-subscribed, don't CC them.
      return $xactions;
    }

    $should_cc = false;
    foreach ($xactions as $xaction) {
      if ($this->shouldImplyCC($object, $xaction)) {
        $should_cc = true;
        break;
      }
    }

    if (!$should_cc) {
      // Only some types of actions imply a CC (like adding a comment).
      return $xactions;
    }

    if ($object->getPHID()) {
      if (isset($this->subscribers[$actor_phid])) {
        // If the user is already subscribed, don't implicitly CC them.
        return $xactions;
      }

      $unsub = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $object->getPHID(),
        PhabricatorEdgeConfig::TYPE_OBJECT_HAS_UNSUBSCRIBER);
      $unsub = array_fuse($unsub);
      if (isset($unsub[$actor_phid])) {
        // If the user has previously unsubscribed from this object explicitly,
        // don't implicitly CC them.
        return $xactions;
      }
    }

    $xaction = newv(get_class(head($xactions)), array());
    $xaction->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS);
    $xaction->setNewValue(array('+' => array($actor_phid)));

    array_unshift($xactions, $xaction);

    return $xactions;
  }

  protected function shouldImplyCC(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return true;
      default:
        return false;
    }
  }


/* -(  Sending Mail  )------------------------------------------------------- */


  /**
   * @task mail
   */
  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }


  /**
   * @task mail
   */
  protected function sendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $email_to = array_filter(array_unique($this->getMailTo($object)));
    $email_cc = array_filter(array_unique($this->getMailCC($object)));

    $phids = array_merge($email_to, $email_cc);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs($phids)
      ->execute();

    $template = $this->buildMailTemplate($object);
    $body = $this->buildMailBody($object, $xactions);

    $mail_tags = $this->getMailTags($object, $xactions);

    $action = $this->getStrongestAction($object, $xactions)->getActionName();

    $template
      ->setFrom($this->requireActor()->getPHID())
      ->setSubjectPrefix($this->getMailSubjectPrefix())
      ->setVarySubjectPrefix('['.$action.']')
      ->setThreadID($this->getMailThreadID($object), $this->getIsNewObject())
      ->setRelatedPHID($object->getPHID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setMailTags($mail_tags)
      ->setIsBulk(true)
      ->setBody($body->render());

    if ($this->getParentMessageID()) {
      $template->setParentMessageID($this->getParentMessageID());
    }

    $mails = $this
      ->buildReplyHandler($object)
      ->multiplexMail(
        $template,
        array_select_keys($handles, $email_to),
        array_select_keys($handles, $email_cc));

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }

    $template->addTos($email_to);
    $template->addCCs($email_cc);

    return $template;
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    return $object->getPHID();
  }


  /**
   * @task mail
   */
  protected function getStrongestAction(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return last(msort($xactions, 'getActionStrength'));
  }


  /**
   * @task mail
   */
  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    throw new Exception("Capability not supported.");
  }


  /**
   * @task mail
   */
  protected function getMailSubjectPrefix() {
    throw new Exception("Capability not supported.");
  }


  /**
   * @task mail
   */
  protected function getMailTags(
    PhabricatorLiskDAO $object,
    array $xactions) {
    $tags = array();

    foreach ($xactions as $xaction) {
      $tags[] = $xaction->getMailTags();
    }

    return array_mergev($tags);
  }


  /**
   * @task mail
   */
  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    throw new Exception("Capability not supported.");
  }


  /**
   * @task mail
   */
  protected function getMailTo(PhabricatorLiskDAO $object) {
    throw new Exception("Capability not supported.");
  }


  /**
   * @task mail
   */
  protected function getMailCC(PhabricatorLiskDAO $object) {
    if ($object instanceof PhabricatorSubscribableInterface) {
      return $this->subscribers;
    }
    throw new Exception("Capability not supported.");
  }


  /**
   * @task mail
   */
  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $headers = array();
    $comments = array();

    foreach ($xactions as $xaction) {
      if ($xaction->shouldHideForMail()) {
        continue;
      }
      $headers[] = id(clone $xaction)->setRenderingTarget('text')->getTitle();
      $comment = $xaction->getComment();
      if ($comment && strlen($comment->getContent())) {
        $comments[] = $comment->getContent();
      }
    }

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection(implode("\n", $headers));

    foreach ($comments as $comment) {
      $body->addRawSection($comment);
    }

    return $body;
  }


/* -(  Publishing Feed Stories  )-------------------------------------------- */


  /**
   * @task feed
   */
  protected function supportsFeed() {
    return false;
  }


  /**
   * @task feed
   */
  protected function getFeedStoryType() {
    return 'PhabricatorApplicationTransactionFeedStory';
  }


  /**
   * @task feed
   */
  protected function getFeedRelatedPHIDs(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return array(
      $object->getPHID(),
      $this->requireActor()->getPHID(),
    );
  }


  /**
   * @task feed
   */
  protected function getFeedNotifyPHIDs(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return array_unique(array_merge(
      $this->getMailTo($object),
      $this->getMailCC($object)));
  }


  /**
   * @task feed
   */
  protected function getFeedStoryData(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $xactions = msort($xactions, 'getActionStrength');
    $xactions = array_reverse($xactions);

    return array(
      'objectPHID'        => $object->getPHID(),
      'transactionPHIDs'  => mpull($xactions, 'getPHID'),
    );
  }


  /**
   * @task feed
   */
  protected function publishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions,
    array $mailed_phids) {

    $related_phids = $this->getFeedRelatedPHIDs($object, $xactions);
    $subscribed_phids = $this->getFeedNotifyPHIDs($object, $xactions);

    $story_type = $this->getFeedStoryType();
    $story_data = $this->getFeedStoryData($object, $xactions);

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType($story_type)
      ->setStoryData($story_data)
      ->setStoryTime(time())
      ->setStoryAuthorPHID($this->requireActor()->getPHID())
      ->setRelatedPHIDs($related_phids)
      ->setPrimaryObjectPHID($object->getPHID())
      ->setSubscribedPHIDs($subscribed_phids)
      ->setMailRecipientPHIDs($mailed_phids)
      ->publish();
  }


/* -(  Search Index  )------------------------------------------------------- */


  /**
   * @task search
   */
  protected function supportsSearch() {
    return false;
  }


/* -(  Herald Integration )-------------------------------------------------- */


  protected function supportsHerald() {
    return false;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {
    throw new Exception('No herald adapter specified.');
  }

  private function setHeraldAdapter(HeraldAdapter $adapter) {
    $this->heraldAdapter = $adapter;
    return $this;
  }

  protected function getHeraldAdapter() {
    return $this->heraldAdapter;
  }

  private function setHeraldTranscript(HeraldTranscript $transcript) {
    $this->heraldTranscript = $transcript;
    return $this;
  }

  protected function getHeraldTranscript() {
    return $this->heraldTranscript;
  }

  private function applyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $adapter = $this->buildHeraldAdapter($object, $xactions);
    $adapter->setContentSource($this->getContentSource());
    $xscript = HeraldEngine::loadAndApplyRules($adapter);

    $this->setHeraldAdapter($adapter);
    $this->setHeraldTranscript($xscript);

    $this->didApplyHeraldRules($object, $adapter, $xscript);
  }

  protected function didApplyHeraldRules(
    PhabricatorLiskDAO $object,
    HeraldAdapter $adapter,
    HeraldTranscript $transcript) {

  }


/* -(  Custom Fields  )------------------------------------------------------ */


  /**
   * @task customfield
   */
  private function getCustomFieldForTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $field_key = $xaction->getMetadataValue('customfield:key');
    if (!$field_key) {
      throw new Exception(
        "Custom field transaction has no 'customfield:key'!");
    }

    $field = PhabricatorCustomField::getObjectField(
      $object,
      PhabricatorCustomField::ROLE_APPLICATIONTRANSACTIONS,
      $field_key);

    if (!$field) {
      throw new Exception(
        "Custom field transaction has invalid 'customfield:key'; field ".
        "'{$field_key}' is disabled or does not exist.");
    }

    if (!$field->shouldAppearInApplicationTransactions()) {
      throw new Exception(
        "Custom field transaction '{$field_key}' does not implement ".
        "integration for ApplicationTransactions.");
    }

    return $field;
  }


/* -(  Files  )-------------------------------------------------------------- */


  /**
   * Extract the PHIDs of any files which these transactions attach.
   *
   * @task files
   */
  private function extractFilePHIDs(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $blocks = array();
    foreach ($xactions as $xaction) {
      $blocks[] = $this->getRemarkupBlocksFromTransaction($xaction);
    }
    $blocks = array_mergev($blocks);


    $phids = array();
    if ($blocks) {
      $phids[] = PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
        $blocks);
    }

    foreach ($xactions as $xaction) {
      $phids[] = $this->extractFilePHIDsFromCustomTransaction(
        $object,
        $xaction);
    }

    $phids = array_unique(array_filter(array_mergev($phids)));
    if (!$phids) {
      return array();
    }

    // Only let a user attach files they can actually see, since this would
    // otherwise let you access any file by attaching it to an object you have
    // view permission on.

    $files = id(new PhabricatorFileQuery())
      ->setViewer($this->getActor())
      ->withPHIDs($phids)
      ->execute();

    return mpull($files, 'getPHID');
  }

  /**
   * @task files
   */
  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return array();
  }


  /**
   * @task files
   */
  private function attachFiles(
    PhabricatorLiskDAO $object,
    array $file_phids) {

    if (!$file_phids) {
      return;
    }

    $editor = id(new PhabricatorEdgeEditor())
      ->setActor($this->getActor());

    // TODO: Edge-based events were almost certainly a terrible idea. If we
    // don't suppress this event, the Maniphest listener reenters and adds
    // more transactions. Just suppress it until that can get cleaned up.
    $editor->setSuppressEvents(true);

    $src = $object->getPHID();
    $type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_FILE;
    foreach ($file_phids as $dst) {
      $editor->addEdge($src, $type, $dst);
    }

    $editor->save();
  }

}
