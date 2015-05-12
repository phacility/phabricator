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
  private $unmentionablePHIDMap = array();
  private $applicationEmail;

  private $isPreview;
  private $isHeraldEditor;
  private $isInverseEdgeEditor;
  private $actingAsPHID;
  private $disableEmail;


  /**
   * Get the class name for the application this editor is a part of.
   *
   * Uninstalling the application will disable the editor.
   *
   * @return string Editor's application class name.
   */
  abstract public function getEditorApplicationClass();


  /**
   * Get a description of the objects this editor edits, like "Differential
   * Revisions".
   *
   * @return string Human readable description of edited objects.
   */
  abstract public function getEditorObjectsDescription();


  public function setActingAsPHID($acting_as_phid) {
    $this->actingAsPHID = $acting_as_phid;
    return $this;
  }

  public function getActingAsPHID() {
    if ($this->actingAsPHID) {
      return $this->actingAsPHID;
    }
    return $this->getActor()->getPHID();
  }


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

  public function getIsNewObject() {
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

  public function setIsInverseEdgeEditor($is_inverse_edge_editor) {
    $this->isInverseEdgeEditor = $is_inverse_edge_editor;
    return $this;
  }

  public function getIsInverseEdgeEditor() {
    return $this->isInverseEdgeEditor;
  }

  public function setIsHeraldEditor($is_herald_editor) {
    $this->isHeraldEditor = $is_herald_editor;
    return $this;
  }

  public function getIsHeraldEditor() {
    return $this->isHeraldEditor;
  }

  /**
   * Prevent this editor from generating email when applying transactions.
   *
   * @param bool  True to disable email.
   * @return this
   */
  public function setDisableEmail($disable_email) {
    $this->disableEmail = $disable_email;
    return $this;
  }

  public function getDisableEmail() {
    return $this->disableEmail;
  }

  public function setUnmentionablePHIDMap(array $map) {
    $this->unmentionablePHIDMap = $map;
    return $this;
  }

  public function getUnmentionablePHIDMap() {
    return $this->unmentionablePHIDMap;
  }

  protected function shouldEnableMentions(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  public function setApplicationEmail(
    PhabricatorMetaMTAApplicationEmail $email) {
    $this->applicationEmail = $email;
    return $this;
  }

  public function getApplicationEmail() {
    return $this->applicationEmail;
  }

  public function getTransactionTypes() {
    $types = array();

    if ($this->object instanceof PhabricatorSubscribableInterface) {
      $types[] = PhabricatorTransactions::TYPE_SUBSCRIBERS;
    }

    if ($this->object instanceof PhabricatorCustomFieldInterface) {
      $types[] = PhabricatorTransactions::TYPE_CUSTOMFIELD;
    }

    if ($this->object instanceof HarbormasterBuildableInterface) {
      $types[] = PhabricatorTransactions::TYPE_BUILDABLE;
    }

    if ($this->object instanceof PhabricatorTokenReceiverInterface) {
      $types[] = PhabricatorTransactions::TYPE_TOKEN;
    }

    if ($this->object instanceof PhabricatorProjectInterface ||
        $this->object instanceof PhabricatorMentionableInterface) {
      $types[] = PhabricatorTransactions::TYPE_EDGE;
    }

    return $types;
  }

  private function adjustTransactionValues(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    if ($xaction->shouldGenerateOldValue()) {
      $old = $this->getTransactionOldValue($object, $xaction);
      $xaction->setOldValue($old);
    }

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
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return $object->getJoinPolicy();
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
      case PhabricatorTransactions::TYPE_COMMENT:
        return null;
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
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_BUILDABLE:
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_INLINESTATE:
        return $xaction->getNewValue();
      case PhabricatorTransactions::TYPE_EDGE:
        return $this->getEdgeTransactionNewValue($xaction);
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->getNewValueFromApplicationTransactions($xaction);
      case PhabricatorTransactions::TYPE_COMMENT:
        return null;
      default:
        return $this->getCustomTransactionNewValue($object, $xaction);
    }
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception('Capability not supported!');
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception('Capability not supported!');
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
      case PhabricatorTransactions::TYPE_EDGE:
        // A straight value comparison here doesn't always get the right
        // result, because newly added edges aren't fully populated. Instead,
        // compare the changes in a more granular way.
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();

        $old_dst = array_keys($old);
        $new_dst = array_keys($new);

        // NOTE: For now, we don't consider edge reordering to be a change.
        // We have very few order-dependent edges and effectively no order
        // oriented UI. This might change in the future.
        sort($old_dst);
        sort($new_dst);

        if ($old_dst !== $new_dst) {
          // We've added or removed edges, so this transaction definitely
          // has an effect.
          return true;
        }

        // We haven't added or removed edges, but we might have changed
        // edge data.
        foreach ($old as $key => $old_value) {
          $new_value = $new[$key];
          if ($old_value['data'] !== $new_value['data']) {
            return true;
          }
        }

        return false;
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
    throw new PhutilMethodNotImplementedException();
  }

  private function applyInternalEffects(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_BUILDABLE:
      case PhabricatorTransactions::TYPE_TOKEN:
        return;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        $object->setJoinPolicy($xaction->getNewValue());
        break;

      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->applyApplicationTransactionInternalEffects($xaction);
      case PhabricatorTransactions::TYPE_INLINESTATE:
        return $this->applyBuiltinInternalTransaction($object, $xaction);
    }

    return $this->applyCustomInternalTransaction($object, $xaction);
  }

  private function applyExternalEffects(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_BUILDABLE:
      case PhabricatorTransactions::TYPE_TOKEN:
        return;
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
        if ($this->getIsInverseEdgeEditor()) {
          // If we're writing an inverse edge transaction, don't actually
          // do anything. The initiating editor on the other side of the
          // transaction will take care of the edge writes.
          break;
        }

        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();
        $src = $object->getPHID();
        $const = $xaction->getMetadataValue('edge:type');

        $type = PhabricatorEdgeType::getByConstant($const);
        if ($type->shouldWriteInverseTransactions()) {
          $this->applyInverseEdgeTransactions(
            $object,
            $xaction,
            $type->getInverseEdgeConstant());
        }

        foreach ($new as $dst_phid => $edge) {
          $new[$dst_phid]['src'] = $src;
        }

        $editor = new PhabricatorEdgeEditor();

        foreach ($old as $dst_phid => $edge) {
          if (!empty($new[$dst_phid])) {
            if ($old[$dst_phid]['data'] === $new[$dst_phid]['data']) {
              continue;
            }
          }
          $editor->removeEdge($src, $const, $dst_phid);
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

          $editor->addEdge($src, $const, $dst_phid, $data);
        }

        $editor->save();
        break;
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->applyApplicationTransactionExternalEffects($xaction);
      case PhabricatorTransactions::TYPE_INLINESTATE:
        return $this->applyBuiltinExternalTransaction($object, $xaction);
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

  // TODO: Write proper documentation for these hooks. These are like the
  // "applyCustom" hooks, except that implementation is optional, so you do
  // not need to handle all of the builtin transaction types. See T6403. These
  // are not completely implemented.

  protected function applyBuiltinInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function applyBuiltinExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  /**
   * Fill in a transaction's common values, like author and content source.
   */
  protected function populateTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $actor = $this->getActor();

    // TODO: This needs to be more sophisticated once we have meta-policies.
    $xaction->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC);

    if ($actor->isOmnipotent()) {
      $xaction->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    } else {
      $xaction->setEditPolicy($this->getActingAsPHID());
    }

    $xaction->setAuthorPHID($this->getActingAsPHID());
    $xaction->setContentSource($this->getContentSource());
    $xaction->attachViewer($actor);
    $xaction->attachObject($object);

    if ($object->getPHID()) {
      $xaction->setObjectPHID($object->getPHID());
    }

    return $xaction;
  }

  protected function didApplyInternalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $xactions;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $xactions;
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function setContentSourceFromRequest(AphrontRequest $request) {
    return $this->setContentSource(
      PhabricatorContentSource::newFromRequest($request));
  }

  public function setContentSourceFromConduitRequest(
    ConduitAPIRequest $request) {

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_CONDUIT,
      array());

    return $this->setContentSource($content_source);
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

    // NOTE: Some transaction expansion requires that the edited object be
    // attached.
    foreach ($xactions as $xaction) {
      $xaction->attachObject($object);
      $xaction->attachViewer($actor);
    }

    $xactions = $this->expandTransactions($object, $xactions);
    $xactions = $this->expandSupportTransactions($object, $xactions);
    $xactions = $this->combineTransactions($xactions);

    foreach ($xactions as $xaction) {
      $xaction = $this->populateTransaction($object, $xaction);
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

      $errors[] = $this->validateAllTransactions($object, $xactions);
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
      ->setActingAsPHID($this->getActingAsPHID())
      ->setContentSource($this->getContentSource());

    if (!$transaction_open) {
      $object->openTransaction();
    }

      foreach ($xactions as $xaction) {
        $this->applyInternalEffects($object, $xaction);
      }

      $xactions = $this->didApplyInternalEffects($object, $xactions);

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

      $xactions = $this->applyFinalEffects($object, $xactions);

      if ($read_locking) {
        $object->endReadLocking();
        $read_locking = false;
      }

    $object->saveTransaction();

    // Now that we've completely applied the core transaction set, try to apply
    // Herald rules. Herald rules are allowed to either take direct actions on
    // the database (like writing flags), or take indirect actions (like saving
    // some targets for CC when we generate mail a little later), or return
    // transactions which we'll apply normally using another Editor.

    // First, check if *this* is a sub-editor which is itself applying Herald
    // rules: if it is, stop working and return so we don't descend into
    // madness.

    // Otherwise, we're not a Herald editor, so process Herald rules (possibly
    // using a Herald editor to apply resulting transactions) and then send out
    // mail, notifications, and feed updates about everything.

    if ($this->getIsHeraldEditor()) {
      // We are the Herald editor, so stop work here and return the updated
      // transactions.
      return $xactions;
    } else if ($this->getIsInverseEdgeEditor()) {
      // If we're applying inverse edge transactions, don't trigger Herald.
      // From a product perspective, the current set of inverse edges (most
      // often, mentions) aren't things users would expect to trigger Herald.
      // From a technical perspective, objects loaded by the inverse editor may
      // not have enough data to execute rules. At least for now, just stop
      // Herald from executing when applying inverse edges.
    } else if ($this->shouldApplyHeraldRules($object, $xactions)) {
      // We are not the Herald editor, so try to apply Herald rules.
      $herald_xactions = $this->applyHeraldRules($object, $xactions);

      if ($herald_xactions) {
        $xscript_id = $this->getHeraldTranscript()->getID();
        foreach ($herald_xactions as $herald_xaction) {
          $herald_xaction->setMetadataValue('herald:transcriptID', $xscript_id);
        }

        // NOTE: We're acting as the omnipotent user because rules deal with
        // their own policy issues. We use a synthetic author PHID (the
        // Herald application) as the author of record, so that transactions
        // will render in a reasonable way ("Herald assigned this task ...").
        $herald_actor = PhabricatorUser::getOmnipotentUser();
        $herald_phid = id(new PhabricatorHeraldApplication())->getPHID();

        // TODO: It would be nice to give transactions a more specific source
        // which points at the rule which generated them. You can figure this
        // out from transcripts, but it would be cleaner if you didn't have to.

        $herald_source = PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_HERALD,
          array());

        $herald_editor = newv(get_class($this), array())
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true)
          ->setParentMessageID($this->getParentMessageID())
          ->setIsHeraldEditor(true)
          ->setActor($herald_actor)
          ->setActingAsPHID($herald_phid)
          ->setContentSource($herald_source);

        $herald_xactions = $herald_editor->applyTransactions(
          $object,
          $herald_xactions);

        // Merge the new transactions into the transaction list: we want to
        // send email and publish feed stories about them, too.
        $xactions = array_merge($xactions, $herald_xactions);
      }
    }

    // Before sending mail or publishing feed stories, reload the object
    // subscribers to pick up changes caused by Herald (or by other side effects
    // in various transaction phases).
    $this->loadSubscribers($object);

    $this->loadHandles($xactions);

    $mail = null;
    if (!$this->getDisableEmail()) {
      if ($this->shouldSendMail($object, $xactions)) {
        $mail = $this->sendMail($object, $xactions);
      }
    }

    if ($this->supportsSearch()) {
      id(new PhabricatorSearchIndexer())
        ->queueDocumentForIndexing(
          $object->getPHID(),
          $this->getSearchContextParameter($object, $xactions));
    }

    if ($this->shouldPublishFeedStory($object, $xactions)) {
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

      // It now happens in the search indexer as well, but the search indexer is
      // always daemonized, so the logic above still potentially holds. We could
      // possibly get rid of this. The major motivation for putting it in the
      // indexer was to enable reindexing to work.

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
        'Call setContentSource() before applyTransactions()!');
    }

    // Do a bunch of sanity checks that the incoming transactions are fresh.
    // They should be unsaved and have only "transactionType" and "newValue"
    // set.

    $types = array_fill_keys($this->getTransactionTypes(), true);

    assert_instances_of($xactions, 'PhabricatorApplicationTransaction');
    foreach ($xactions as $xaction) {
      if ($xaction->getPHID() || $xaction->getID()) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'You can not apply transactions which already have IDs/PHIDs!'));
      }
      if ($xaction->getObjectPHID()) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'You can not apply transactions which already have objectPHIDs!'));
      }
      if ($xaction->getAuthorPHID()) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'You can not apply transactions which already have authorPHIDs!'));
      }
      if ($xaction->getCommentPHID()) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'You can not apply transactions which already have '.
            'commentPHIDs!'));
      }
      if ($xaction->getCommentVersion() !== 0) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'You can not apply transactions which already have '.
            'commentVersions!'));
      }

      $expect_value = !$xaction->shouldGenerateOldValue();
      $has_value = $xaction->hasOldValue();

      if ($expect_value && !$has_value) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'This transaction is supposed to have an oldValue set, but '.
            'it does not!'));
      }

      if ($has_value && !$expect_value) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'This transaction should generate its oldValue automatically, '.
            'but has already had one set!'));
      }

      $type = $xaction->getTransactionType();
      if (empty($types[$type])) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'Transaction has type "%s", but that transaction type is not '.
            'supported by this editor (%s).',
            $type,
            get_class($this)));
      }
    }
  }

  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    if ($this->getIsNewObject()) {
      return;
    }

    $actor = $this->requireActor();
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        PhabricatorPolicyFilter::requireCapability(
          $actor,
          $object,
          PhabricatorPolicyCapability::CAN_VIEW);
        break;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        PhabricatorPolicyFilter::requireCapability(
          $actor,
          $object,
          PhabricatorPolicyCapability::CAN_EDIT);
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        PhabricatorPolicyFilter::requireCapability(
          $actor,
          $object,
          PhabricatorPolicyCapability::CAN_EDIT);
        break;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        PhabricatorPolicyFilter::requireCapability(
          $actor,
          $object,
          PhabricatorPolicyCapability::CAN_EDIT);
        break;
    }
  }

  private function buildSubscribeTransaction(
    PhabricatorLiskDAO $object,
    array $xactions,
    array $blocks) {

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      return null;
    }

    if ($this->shouldEnableMentions($object, $xactions)) {
      $texts = array_mergev($blocks);
      $phids = PhabricatorMarkupEngine::extractPHIDsFromMentions(
        $this->getActor(),
        $texts);
    } else {
      $phids = array();
    }

    $this->mentionedPHIDs = $phids;

    if ($object->getPHID()) {
      // Don't try to subscribe already-subscribed mentions: we want to generate
      // a dialog about an action having no effect if the user explicitly adds
      // existing CCs, but not if they merely mention existing subscribers.
      $phids = array_diff($phids, $this->subscribers);
    }

    if ($phids) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getActor())
        ->withPHIDs($phids)
        ->execute();
      $users = mpull($users, null, 'getPHID');

      foreach ($phids as $key => $phid) {
        // Do not subscribe mentioned users
        // who do not have VIEW Permissions
        if ($object instanceof PhabricatorPolicyInterface
          && !PhabricatorPolicyFilter::hasCapability(
          $users[$phid],
          $object,
          PhabricatorPolicyCapability::CAN_VIEW)
        ) {
          unset($phids[$key]);
        } else {
          if ($object->isAutomaticallySubscribed($phid)) {
            unset($phids[$key]);
          }
        }
      }
      $phids = array_values($phids);
    }
    // No else here to properly return null should we unset all subscriber
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
    return $transaction->getRemarkupBlocks();
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
   * Optionally expand transactions which imply other effects. For example,
   * resigning from a revision in Differential implies removing yourself as
   * a reviewer.
   */
  private function expandTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $results = array();
    foreach ($xactions as $xaction) {
      foreach ($this->expandTransaction($object, $xaction) as $expanded) {
        $results[] = $expanded;
      }
    }

    return $results;
  }

  protected function expandTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return array($xaction);
  }


  public function getExpandedSupportTransactions(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $xactions = array($xaction);
    $xactions = $this->expandSupportTransactions(
      $object,
      $xactions);

    if (count($xactions) == 1) {
      return array();
    }

    foreach ($xactions as $index => $cxaction) {
      if ($cxaction === $xaction) {
        unset($xactions[$index]);
        break;
      }
    }

    return $xactions;
  }

  private function expandSupportTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {
    $this->loadSubscribers($object);

    $xactions = $this->applyImplicitCC($object, $xactions);

    $blocks = array();
    foreach ($xactions as $key => $xaction) {
      $blocks[$key] = $this->getRemarkupBlocksFromTransaction($xaction);
    }

    $subscribe_xaction = $this->buildSubscribeTransaction(
      $object,
      $xactions,
      $blocks);
    if ($subscribe_xaction) {
      $xactions[] = $subscribe_xaction;
    }

    // TODO: For now, this is just a placeholder.
    $engine = PhabricatorMarkupEngine::getEngine('extract');
    $engine->setConfig('viewer', $this->requireActor());

    $block_xactions = $this->expandRemarkupBlockTransactions(
      $object,
      $xactions,
      $blocks,
      $engine);

    foreach ($block_xactions as $xaction) {
      $xactions[] = $xaction;
    }

    return $xactions;
  }

  private function expandRemarkupBlockTransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    $blocks,
    PhutilMarkupEngine $engine) {

    $block_xactions = $this->expandCustomRemarkupBlockTransactions(
      $object,
      $xactions,
      $blocks,
      $engine);

    $mentioned_phids = array();
    if ($this->shouldEnableMentions($object, $xactions)) {
      foreach ($blocks as $key => $xaction_blocks) {
        foreach ($xaction_blocks as $block) {
          $engine->markupText($block);
          $mentioned_phids += $engine->getTextMetadata(
            PhabricatorObjectRemarkupRule::KEY_MENTIONED_OBJECTS,
            array());
        }
      }
    }

    if (!$mentioned_phids) {
      return $block_xactions;
    }

    $mentioned_objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getActor())
      ->withPHIDs($mentioned_phids)
      ->execute();

    $mentionable_phids = array();
    if ($this->shouldEnableMentions($object, $xactions)) {
      foreach ($mentioned_objects as $mentioned_object) {
        if ($mentioned_object instanceof PhabricatorMentionableInterface) {
          $mentioned_phid = $mentioned_object->getPHID();
          if (idx($this->getUnmentionablePHIDMap(), $mentioned_phid)) {
            continue;
          }
          // don't let objects mention themselves
          if ($object->getPHID() && $mentioned_phid == $object->getPHID()) {
            continue;
          }
          $mentionable_phids[$mentioned_phid] = $mentioned_phid;
        }
      }
    }

    if ($mentionable_phids) {
      $edge_type = PhabricatorObjectMentionsObjectEdgeType::EDGECONST;
      $block_xactions[] = newv(get_class(head($xactions)), array())
        ->setIgnoreOnNoEffect(true)
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $edge_type)
        ->setNewValue(array('+' => $mentionable_phids));
    }

    return $block_xactions;
  }

  protected function expandCustomRemarkupBlockTransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    $blocks,
    PhutilMarkupEngine $engine) {
    return array();
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
      if ($u->getTransactionType() == PhabricatorTransactions::TYPE_EDGE) {
        if (empty($result[$key])) {
          $result[$key] = $value;
        } else {
          // We're merging two lists of edge adds, sets, or removes. Merge
          // them by merging individual PHIDs within them.
          $merged = $result[$key];

          foreach ($value as $dst => $v_spec) {
            if (empty($merged[$dst])) {
              $merged[$dst] = $v_spec;
            } else {
              // Two transactions are trying to perform the same operation on
              // the same edge. Normalize the edge data and then merge it. This
              // allows transactions to specify how data merges execute in a
              // precise way.

              $u_spec = $merged[$dst];

              if (!is_array($u_spec)) {
                $u_spec = array('dst' => $u_spec);
              }
              if (!is_array($v_spec)) {
                $v_spec = array('dst' => $v_spec);
              }

              $ux_data = idx($u_spec, 'data', array());
              $vx_data = idx($v_spec, 'data', array());

              $merged_data = $this->mergeEdgeData(
                $u->getMetadataValue('edge:type'),
                $ux_data,
                $vx_data);

              $u_spec['data'] = $merged_data;
              $merged[$dst] = $u_spec;
            }
          }

          $result[$key] = $merged;
        }
      } else {
        $result[$key] = array_merge($value, idx($result, $key, array()));
      }
    }
    $u->setNewValue($result);

    // When combining an "ignore" transaction with a normal transaction, make
    // sure we don't propagate the "ignore" flag.
    if (!$v->getIgnoreOnNoEffect()) {
      $u->setIgnoreOnNoEffect(false);
    }

    return $u;
  }

  protected function mergeEdgeData($type, array $u, array $v) {
    return $v + $u;
  }

  protected function getPHIDTransactionNewValue(
    PhabricatorApplicationTransaction $xaction,
    $old = null) {

    if ($old !== null) {
      $old = array_fuse($old);
    } else {
      $old = array_fuse($xaction->getOldValue());
    }

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
        $edge,
        $dst_phid);
    }

    if ($new_set !== null) {
      foreach ($new_set as $dst_phid => $edge) {
        $result[$dst_phid] = $this->normalizeEdgeTransactionValue(
          $xaction,
          $edge,
          $dst_phid);
      }
    }

    foreach ($new_add as $dst_phid => $edge) {
      $result[$dst_phid] = $this->normalizeEdgeTransactionValue(
        $xaction,
        $edge,
        $dst_phid);
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

  private function normalizeEdgeTransactionValue(
    PhabricatorApplicationTransaction $xaction,
    $edge,
    $dst_phid) {

    if (!is_array($edge)) {
      if ($edge != $dst_phid) {
        throw new Exception(
          pht(
            'Transaction edge data must either be the edge PHID or an edge '.
            'specification dictionary.'));
      }
      $edge = array();
    } else {
      foreach ($edge as $key => $value) {
        switch ($key) {
          case 'src':
          case 'dst':
          case 'type':
          case 'data':
          case 'dateCreated':
          case 'dateModified':
          case 'seq':
          case 'dataID':
            break;
          default:
            throw new Exception(
              pht(
                'Transaction edge specification contains unexpected key '.
                '"%s".',
                $key));
        }
      }
    }

    $edge['dst'] = $dst_phid;

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
      $edge['data'] = array();
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
      } else if ($xaction->getIgnoreOnNoEffect()) {
        unset($xactions[$key]);
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
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $errors[] = $this->validatePolicyTransaction(
          $object,
          $xactions,
          $type,
          PhabricatorPolicyCapability::CAN_VIEW);
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $errors[] = $this->validatePolicyTransaction(
          $object,
          $xactions,
          $type,
          PhabricatorPolicyCapability::CAN_EDIT);
        break;
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $groups = array();
        foreach ($xactions as $xaction) {
          $groups[$xaction->getMetadataValue('customfield:key')][] = $xaction;
        }

        $field_list = PhabricatorCustomField::getObjectFields(
          $object,
          PhabricatorCustomField::ROLE_EDIT);
        $field_list->setViewer($this->getActor());

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

  private function validatePolicyTransaction(
    PhabricatorLiskDAO $object,
    array $xactions,
    $transaction_type,
    $capability) {

    $actor = $this->requireActor();
    $errors = array();
    // Note $this->xactions is necessary; $xactions is $this->xactions of
    // $transaction_type
    $policy_object = $this->adjustObjectForPolicyChecks(
      $object,
      $this->xactions);

    // Make sure the user isn't editing away their ability to $capability this
    // object.
    foreach ($xactions as $xaction) {
      try {
        PhabricatorPolicyFilter::requireCapabilityWithForcedPolicy(
          $actor,
          $policy_object,
          $capability,
          $xaction->getNewValue());
      } catch (PhabricatorPolicyException $ex) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $transaction_type,
          pht('Invalid'),
          pht(
            'You can not select this %s policy, because you would no longer '.
            'be able to %s the object.',
            $capability,
            $capability),
          $xaction);
      }
    }

    if ($this->getIsNewObject()) {
      if (!$xactions) {
        $has_capability = PhabricatorPolicyFilter::hasCapability(
          $actor,
          $policy_object,
          $capability);
        if (!$has_capability) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $transaction_type,
            pht('Invalid'),
            pht('The selected %s policy excludes you. Choose a %s policy '.
                'which allows you to %s the object.',
            $capability,
            $capability,
            $capability));
        }
      }
    }

    return $errors;
  }

  protected function adjustObjectForPolicyChecks(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return clone $object;
  }

  protected function validateAllTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return array();
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

    $actor_phid = $this->getActingAsPHID();

    $type_user = PhabricatorPeopleUserPHIDType::TYPECONST;
    if (phid_get_type($actor_phid) != $type_user) {
      // Transactions by application actors like Herald, Harbormaster and
      // Diffusion should not CC the applications.
      return $xactions;
    }

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
        PhabricatorObjectHasUnsubscriberEdgeType::EDGECONST);
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

    return $xaction->isCommentTransaction();
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

    // Check if any of the transactions are visible. If we don't have any
    // visible transactions, don't send the mail.

    $any_visible = false;
    foreach ($xactions as $xaction) {
      if (!$xaction->shouldHideForMail($xactions)) {
        $any_visible = true;
        break;
      }
    }

    if (!$any_visible) {
      return;
    }

    $email_force = array();
    $email_to = $this->getMailTo($object);
    $email_cc = $this->getMailCC($object);

    $adapter = $this->getHeraldAdapter();
    if ($adapter) {
      $email_cc = array_merge($email_cc, $adapter->getEmailPHIDs());
      $email_force = $adapter->getForcedEmailPHIDs();
    }

    $phids = array_merge($email_to, $email_cc);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs($phids)
      ->execute();

    $template = $this->buildMailTemplate($object);
    $body = $this->buildMailBody($object, $xactions);

    $mail_tags = $this->getMailTags($object, $xactions);
    $action = $this->getMailAction($object, $xactions);

    $reply_handler = $this->buildReplyHandler($object);

    $body->addEmailPreferenceSection();

    $template
      ->setFrom($this->getActingAsPHID())
      ->setSubjectPrefix($this->getMailSubjectPrefix())
      ->setVarySubjectPrefix('['.$action.']')
      ->setThreadID($this->getMailThreadID($object), $this->getIsNewObject())
      ->setRelatedPHID($object->getPHID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setForceHeraldMailRecipientPHIDs($email_force)
      ->setMailTags($mail_tags)
      ->setIsBulk(true)
      ->setBody($body->render())
      ->setHTMLBody($body->renderHTML());

    foreach ($body->getAttachments() as $attachment) {
      $template->addAttachment($attachment);
    }

    $herald_xscript = $this->getHeraldTranscript();
    if ($herald_xscript) {
      $herald_header = $herald_xscript->getXHeraldRulesHeader();
      $herald_header = HeraldTranscript::saveXHeraldRulesHeader(
        $object->getPHID(),
        $herald_header);
    } else {
      $herald_header = HeraldTranscript::loadXHeraldRulesHeader(
        $object->getPHID());
    }

    if ($herald_header) {
      $template->addHeader('X-Herald-Rules', $herald_header);
    }

    if ($object instanceof PhabricatorProjectInterface) {
      $this->addMailProjectMetadata($object, $template);
    }

    if ($this->getParentMessageID()) {
      $template->setParentMessageID($this->getParentMessageID());
    }

    $mails = $reply_handler->multiplexMail(
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

  private function addMailProjectMetadata(
    PhabricatorLiskDAO $object,
    PhabricatorMetaMTAMail $template) {

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);

    if (!$project_phids) {
      return;
    }

    // TODO: This viewer isn't quite right. It would be slightly better to use
    // the mail recipient, but that's not very easy given the way rendering
    // works today.

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs($project_phids)
      ->execute();

    $project_tags = array();
    foreach ($handles as $handle) {
      if (!$handle->isComplete()) {
        continue;
      }
      $project_tags[] = '<'.$handle->getObjectName().'>';
    }

    if (!$project_tags) {
      return;
    }

    $project_tags = implode(', ', $project_tags);
    $template->addHeader('X-Phabricator-Projects', $project_tags);
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
    throw new Exception('Capability not supported.');
  }

  /**
   * @task mail
   */
  protected function getMailSubjectPrefix() {
    throw new Exception('Capability not supported.');
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
  public function getMailTagsMap() {
    // TODO: We should move shared mail tags, like "comment", here.
    return array();
  }


  /**
   * @task mail
   */
  protected function getMailAction(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $this->getStrongestAction($object, $xactions)->getActionName();
  }


  /**
   * @task mail
   */
  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    throw new Exception('Capability not supported.');
  }


  /**
   * @task mail
   */
  protected function getMailTo(PhabricatorLiskDAO $object) {
    throw new Exception('Capability not supported.');
  }


  /**
   * @task mail
   */
  protected function getMailCC(PhabricatorLiskDAO $object) {
    $phids = array();
    $has_support = false;

    if ($object instanceof PhabricatorSubscribableInterface) {
      $phids[] = $this->subscribers;
      $has_support = true;
    }

    if ($object instanceof PhabricatorProjectInterface) {
      $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $object->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);

      if ($project_phids) {
        $watcher_type = PhabricatorObjectHasWatcherEdgeType::EDGECONST;

        $query = id(new PhabricatorEdgeQuery())
          ->withSourcePHIDs($project_phids)
          ->withEdgeTypes(array($watcher_type));
        $query->execute();

        $watcher_phids = $query->getDestinationPHIDs();
        if ($watcher_phids) {
          // We need to do a visibility check for all the watchers, as
          // watching a project is not a guarantee that you can see objects
          // associated with it.
          $users = id(new PhabricatorPeopleQuery())
            ->setViewer($this->requireActor())
            ->withPHIDs($watcher_phids)
            ->execute();

          $watchers = array();
          foreach ($users as $user) {
            $can_see = PhabricatorPolicyFilter::hasCapability(
              $user,
              $object,
              PhabricatorPolicyCapability::CAN_VIEW);
            if ($can_see) {
              $watchers[] = $user->getPHID();
            }
          }
          $phids[] = $watchers;
        }
      }

      $has_support = true;
    }

    if (!$has_support) {
      throw new Exception('Capability not supported.');
    }

    return array_mergev($phids);
  }


  /**
   * @task mail
   */
  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = new PhabricatorMetaMTAMailBody();
    $body->setViewer($this->requireActor());

    $this->addHeadersAndCommentsToMailBody($body, $xactions);
    $this->addCustomFieldsToMailBody($body, $object, $xactions);
    return $body;
  }

  /**
   * @task mail
   */
  protected function addHeadersAndCommentsToMailBody(
    PhabricatorMetaMTAMailBody $body,
    array $xactions) {

    $headers = array();
    $comments = array();

    foreach ($xactions as $xaction) {
      if ($xaction->shouldHideForMail($xactions)) {
        continue;
      }

      $header = $xaction->getTitleForMail();
      if ($header !== null) {
        $headers[] = $header;
      }

      $comment = $xaction->getBodyForMail();
      if ($comment !== null) {
        $comments[] = $comment;
      }
    }
    $body->addRawSection(implode("\n", $headers));

    foreach ($comments as $comment) {
      $body->addRemarkupSection($comment);
    }
  }

  /**
   * @task mail
   */
  protected function addCustomFieldsToMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorLiskDAO $object,
    array $xactions) {

    if ($object instanceof PhabricatorCustomFieldInterface) {
      $field_list = PhabricatorCustomField::getObjectFields(
        $object,
        PhabricatorCustomField::ROLE_TRANSACTIONMAIL);
      $field_list->setViewer($this->getActor());
      $field_list->readFieldsFromStorage($object);

      foreach ($field_list->getFields() as $field) {
        $field->updateTransactionMailBody(
          $body,
          $this,
          $xactions);
      }
    }
  }



/* -(  Publishing Feed Stories  )-------------------------------------------- */


  /**
   * @task feed
   */
  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
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

    $phids = array(
      $object->getPHID(),
      $this->getActingAsPHID(),
    );

    if ($object instanceof PhabricatorProjectInterface) {
      $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $object->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      foreach ($project_phids as $project_phid) {
        $phids[] = $project_phid;
      }
    }

    return $phids;
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

    $xactions = mfilter($xactions, 'shouldHideForFeed', true);

    if (!$xactions) {
      return;
    }

    $related_phids = $this->getFeedRelatedPHIDs($object, $xactions);
    $subscribed_phids = $this->getFeedNotifyPHIDs($object, $xactions);

    $story_type = $this->getFeedStoryType();
    $story_data = $this->getFeedStoryData($object, $xactions);

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType($story_type)
      ->setStoryData($story_data)
      ->setStoryTime(time())
      ->setStoryAuthorPHID($this->getActingAsPHID())
      ->setRelatedPHIDs($related_phids)
      ->setPrimaryObjectPHID($object->getPHID())
      ->setSubscribedPHIDs($subscribed_phids)
      ->setMailRecipientPHIDs($mailed_phids)
      ->setMailTags($this->getMailTags($object, $xactions))
      ->publish();
  }


/* -(  Search Index  )------------------------------------------------------- */


  /**
   * @task search
   */
  protected function supportsSearch() {
    return false;
  }

  /**
   * @task search
   */
  protected function getSearchContextParameter(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return null;
  }


/* -(  Herald Integration )-------------------------------------------------- */


  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
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
    $adapter->setIsNewObject($this->getIsNewObject());
    if ($this->getApplicationEmail()) {
      $adapter->setApplicationEmail($this->getApplicationEmail());
    }
    $xscript = HeraldEngine::loadAndApplyRules($adapter);

    $this->setHeraldAdapter($adapter);
    $this->setHeraldTranscript($xscript);

    return array_merge(
      $this->didApplyHeraldRules($object, $adapter, $xscript),
      $adapter->getQueuedTransactions());
  }

  protected function didApplyHeraldRules(
    PhabricatorLiskDAO $object,
    HeraldAdapter $adapter,
    HeraldTranscript $transcript) {
    return array();
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

    $field->setViewer($this->getActor());

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
        $this->getActor(),
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

    $editor = new PhabricatorEdgeEditor();

    $src = $object->getPHID();
    $type = PhabricatorObjectHasFileEdgeType::EDGECONST;
    foreach ($file_phids as $dst) {
      $editor->addEdge($src, $type, $dst);
    }

    $editor->save();
  }

  private function applyInverseEdgeTransactions(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction,
    $inverse_type) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $add = array_keys(array_diff_key($new, $old));
    $rem = array_keys(array_diff_key($old, $new));

    $add = array_fuse($add);
    $rem = array_fuse($rem);
    $all = $add + $rem;

    $nodes = id(new PhabricatorObjectQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs($all)
      ->execute();

    foreach ($nodes as $node) {
      if (!($node instanceof PhabricatorApplicationTransactionInterface)) {
        continue;
      }

      $editor = $node->getApplicationTransactionEditor();
      $template = $node->getApplicationTransactionTemplate();
      $target = $node->getApplicationTransactionObject();

      if (isset($add[$node->getPHID()])) {
        $edge_edit_type = '+';
      } else {
        $edge_edit_type = '-';
      }

      $template
        ->setTransactionType($xaction->getTransactionType())
        ->setMetadataValue('edge:type', $inverse_type)
        ->setNewValue(
          array(
            $edge_edit_type => array($object->getPHID() => $object->getPHID()),
          ));

      $editor
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setParentMessageID($this->getParentMessageID())
        ->setIsInverseEdgeEditor(true)
        ->setActor($this->requireActor())
        ->setActingAsPHID($this->getActingAsPHID())
        ->setContentSource($this->getContentSource());

      $editor->applyTransactions($target, array($template));
    }
  }

}
