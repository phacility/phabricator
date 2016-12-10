<?php

/**
 *
 * Publishing and Managing State
 * ======
 *
 * After applying changes, the Editor queues a worker to publish mail, feed,
 * and notifications, and to perform other background work like updating search
 * indexes. This allows it to do this work without impacting performance for
 * users.
 *
 * When work is moved to the daemons, the Editor state is serialized by
 * @{method:getWorkerState}, then reloaded in a daemon process by
 * @{method:loadWorkerState}. **This is fragile.**
 *
 * State is not persisted into the daemons by default, because we can not send
 * arbitrary objects into the queue. This means the default behavior of any
 * state properties is to reset to their defaults without warning prior to
 * publishing.
 *
 * The easiest way to avoid this is to keep Editors stateless: the overwhelming
 * majority of Editors can be written statelessly. If you need to maintain
 * state, you can either:
 *
 *   - not require state to exist during publishing; or
 *   - pass state to the daemons by implementing @{method:getCustomWorkerState}
 *     and @{method:loadCustomWorkerState}.
 *
 * This architecture isn't ideal, and we may eventually split this class into
 * "Editor" and "Publisher" parts to make it more robust. See T6367 for some
 * discussion and context.
 *
 * @task mail Sending Mail
 * @task feed Publishing Feed Stories
 * @task search Search Index
 * @task files Integration with Files
 * @task workers Managing Workers
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

  private $heraldEmailPHIDs = array();
  private $heraldForcedEmailPHIDs = array();
  private $heraldHeader;
  private $mailToPHIDs = array();
  private $mailCCPHIDs = array();
  private $feedNotifyPHIDs = array();
  private $feedRelatedPHIDs = array();
  private $modularTypes;

  const STORAGE_ENCODING_BINARY = 'binary';

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

  public function getTransactionTypesForObject($object) {
    $old = $this->object;
    try {
      $this->object = $object;
      $result = $this->getTransactionTypes();
      $this->object = $old;
    } catch (Exception $ex) {
      $this->object = $old;
      throw $ex;
    }
    return $result;
  }

  public function getTransactionTypes() {
    $types = array();

    $types[] = PhabricatorTransactions::TYPE_CREATE;

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

    if ($this->object instanceof PhabricatorSpacesInterface) {
      $types[] = PhabricatorTransactions::TYPE_SPACE;
    }

    $template = $this->object->getApplicationTransactionTemplate();
    if ($template instanceof PhabricatorModularTransaction) {
      $xtypes = $template->newModularTransactionTypes();
      foreach ($xtypes as $xtype) {
        $types[] = $xtype->getTransactionTypeConstant();
      }
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

    $type = $xaction->getTransactionType();

    $xtype = $this->getModularTransactionType($type);
    if ($xtype) {
      return $xtype->generateOldValue($object);
    }

    switch ($type) {
      case PhabricatorTransactions::TYPE_CREATE:
        return null;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return array_values($this->subscribers);
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getViewPolicy();
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getEditPolicy();
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getJoinPolicy();
      case PhabricatorTransactions::TYPE_SPACE:
        if ($this->getIsNewObject()) {
          return null;
        }

        $space_phid = $object->getSpacePHID();
        if ($space_phid === null) {
          $default_space = PhabricatorSpacesNamespaceQuery::getDefaultSpace();
          if ($default_space) {
            $space_phid = $default_space->getPHID();
          }
        }

        return $space_phid;
      case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $xaction->getMetadataValue('edge:type');
        if (!$edge_type) {
          throw new Exception(
            pht(
              "Edge transaction has no '%s'!",
              'edge:type'));
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

    $type = $xaction->getTransactionType();

    $xtype = $this->getModularTransactionType($type);
    if ($xtype) {
      return $xtype->generateNewValue($object, $xaction->getNewValue());
    }

    switch ($type) {
      case PhabricatorTransactions::TYPE_CREATE:
        return null;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return $this->getPHIDTransactionNewValue($xaction);
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_BUILDABLE:
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_INLINESTATE:
        return $xaction->getNewValue();
      case PhabricatorTransactions::TYPE_SPACE:
        $space_phid = $xaction->getNewValue();
        if (!strlen($space_phid)) {
          // If an install has no Spaces or the Spaces controls are not visible
          // to the viewer, we might end up with the empty string here instead
          // of a strict `null`, because some controller just used `getStr()`
          // to read the space PHID from the request.
          // Just make this work like callers might reasonably expect so we
          // don't need to handle this specially in every EditController.
          return $this->getActor()->getDefaultSpacePHID();
        } else {
          return $space_phid;
        }
      case PhabricatorTransactions::TYPE_EDGE:
        $new_value = $this->getEdgeTransactionNewValue($xaction);

        $edge_type = $xaction->getMetadataValue('edge:type');
        $type_project = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        if ($edge_type == $type_project) {
          $new_value = $this->applyProjectConflictRules($new_value);
        }

        return $new_value;
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
    throw new Exception(pht('Capability not supported!'));
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception(pht('Capability not supported!'));
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return true;
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

    $type = $xaction->getTransactionType();
    $xtype = $this->getModularTransactionType($type);
    if ($xtype) {
      return $xtype->getTransactionHasEffect(
        $object,
        $xaction->getOldValue(),
        $xaction->getNewValue());
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

    $type = $xaction->getTransactionType();

    $xtype = $this->getModularTransactionType($type);
    if ($xtype) {
      return $xtype->applyInternalEffects($object, $xaction->getNewValue());
    }

    switch ($type) {
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->applyApplicationTransactionInternalEffects($xaction);
      case PhabricatorTransactions::TYPE_CREATE:
      case PhabricatorTransactions::TYPE_BUILDABLE:
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_INLINESTATE:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_SPACE:
      case PhabricatorTransactions::TYPE_COMMENT:
        return $this->applyBuiltinInternalTransaction($object, $xaction);
    }

    return $this->applyCustomInternalTransaction($object, $xaction);
  }

  private function applyExternalEffects(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();

    $xtype = $this->getModularTransactionType($type);
    if ($xtype) {
      return $xtype->applyExternalEffects($object, $xaction->getNewValue());
    }

    switch ($type) {
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
        return $this->applyBuiltinExternalTransaction($object, $xaction);

      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->applyApplicationTransactionExternalEffects($xaction);
      case PhabricatorTransactions::TYPE_CREATE:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_BUILDABLE:
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_INLINESTATE:
      case PhabricatorTransactions::TYPE_SPACE:
      case PhabricatorTransactions::TYPE_COMMENT:
        return $this->applyBuiltinExternalTransaction($object, $xaction);
    }

    return $this->applyCustomExternalTransaction($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    $type = $xaction->getTransactionType();
    throw new Exception(
      pht(
        "Transaction type '%s' is missing an internal apply implementation!",
        $type));
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    $type = $xaction->getTransactionType();
    throw new Exception(
      pht(
        "Transaction type '%s' is missing an external apply implementation!",
        $type));
  }

  /**
   * @{class:PhabricatorTransactions} provides many built-in transactions
   * which should not require much - if any - code in specific applications.
   *
   * This method is a hook for the exceedingly-rare cases where you may need
   * to do **additional** work for built-in transactions. Developers should
   * extend this method, making sure to return the parent implementation
   * regardless of handling any transactions.
   *
   * See also @{method:applyBuiltinExternalTransaction}.
   */
  protected function applyBuiltinInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        $object->setJoinPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_SPACE:
        $object->setSpacePHID($xaction->getNewValue());
        break;
    }
  }

  /**
   * See @{method::applyBuiltinInternalTransaction}.
   */
  protected function applyBuiltinExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
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

        $this->updateWorkboardColumns($object, $const, $old, $new);
        break;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_SPACE:
        $this->scrambleFileSecrets($object);
        break;
    }
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

    // If the transaction already has an explicit author PHID, allow it to
    // stand. This is used by applications like Owners that hook into the
    // post-apply change pipeline.
    if (!$xaction->getAuthorPHID()) {
      $xaction->setAuthorPHID($this->getActingAsPHID());
    }

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

      $this->willApplyTransactions($object, $xactions);

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

    try {
      $xactions = $this->filterTransactions($object, $xactions);
    } catch (Exception $ex) {
      if ($read_locking) {
        $object->endReadLocking();
      }
      if ($transaction_open) {
        $object->killTransaction();
      }
      throw $ex;
    }

    // TODO: Once everything is on EditEngine, just use getIsNewObject() to
    // figure this out instead.
    $mark_as_create = false;
    $create_type = PhabricatorTransactions::TYPE_CREATE;
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $create_type) {
        $mark_as_create = true;
      }
    }

    if ($mark_as_create) {
      foreach ($xactions as $xaction) {
        $xaction->setIsCreateTransaction(true);
      }
    }

    // Now that we've merged, filtered, and combined transactions, check for
    // required capabilities.
    foreach ($xactions as $xaction) {
      $this->requireCapabilities($object, $xaction);
    }

    $xactions = $this->sortTransactions($xactions);
    $file_phids = $this->extractFilePHIDs($object, $xactions);

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

    try {
      foreach ($xactions as $xaction) {
        $this->applyInternalEffects($object, $xaction);
      }

      $xactions = $this->didApplyInternalEffects($object, $xactions);

      try {
        $object->save();
      } catch (AphrontDuplicateKeyQueryException $ex) {
        // This callback has an opportunity to throw a better exception,
        // so execution may end here.
        $this->didCatchDuplicateKeyException($object, $xactions, $ex);

        throw $ex;
      }

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
    } catch (Exception $ex) {
      $object->killTransaction();
      throw $ex;
    }

    // If we need to perform cache engine updates, execute them now.
    id(new PhabricatorCacheEngine())
      ->updateObject($object);

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
          // Don't set a transcript ID if this is a transaction from another
          // application or source, like Owners.
          if ($herald_xaction->getAuthorPHID()) {
            continue;
          }

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
          PhabricatorHeraldContentSource::SOURCECONST);

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

      // If Herald did not generate transactions, we may still need to handle
      // "Send an Email" rules.
      $adapter = $this->getHeraldAdapter();
      $this->heraldEmailPHIDs = $adapter->getEmailPHIDs();
      $this->heraldForcedEmailPHIDs = $adapter->getForcedEmailPHIDs();
    }

    $this->didApplyTransactions($xactions);

    if ($object instanceof PhabricatorCustomFieldInterface) {
      // Maybe this makes more sense to move into the search index itself? For
      // now I'm putting it here since I think we might end up with things that
      // need it to be up to date once the next page loads, but if we don't go
      // there we could move it into search once search moves to the daemons.

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
    $this->heraldHeader = $herald_header;

    // We're going to compute some of the data we'll use to publish these
    // transactions here, before queueing a worker.
    //
    // Primarily, this is more correct: we want to publish the object as it
    // exists right now. The worker may not execute for some time, and we want
    // to use the current To/CC list, not respect any changes which may occur
    // between now and when the worker executes.
    //
    // As a secondary benefit, this tends to reduce the amount of state that
    // Editors need to pass into workers.
    $object = $this->willPublish($object, $xactions);

    if (!$this->getDisableEmail()) {
      if ($this->shouldSendMail($object, $xactions)) {
        $this->mailToPHIDs = $this->getMailTo($object);
        $this->mailCCPHIDs = $this->getMailCC($object);
      }
    }

    if ($this->shouldPublishFeedStory($object, $xactions)) {
      $this->feedRelatedPHIDs = $this->getFeedRelatedPHIDs($object, $xactions);
      $this->feedNotifyPHIDs = $this->getFeedNotifyPHIDs($object, $xactions);
    }

    PhabricatorWorker::scheduleTask(
      'PhabricatorApplicationTransactionPublishWorker',
      array(
        'objectPHID' => $object->getPHID(),
        'actorPHID' => $this->getActingAsPHID(),
        'xactionPHIDs' => mpull($xactions, 'getPHID'),
        'state' => $this->getWorkerState(),
      ),
      array(
        'objectPHID' => $object->getPHID(),
        'priority' => PhabricatorWorker::PRIORITY_ALERTS,
      ));

    return $xactions;
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {
    return;
  }

  public function publishTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $this->object = $object;
    $this->xactions = $xactions;

    // Hook for edges or other properties that may need (re-)loading
    $object = $this->willPublish($object, $xactions);

    // The object might have changed, so reassign it.
    $this->object = $object;

    $messages = array();
    if (!$this->getDisableEmail()) {
      if ($this->shouldSendMail($object, $xactions)) {
        $messages = $this->buildMail($object, $xactions);
      }
    }

    if ($this->supportsSearch()) {
      PhabricatorSearchWorker::queueDocumentForIndexing(
        $object->getPHID(),
        array(
          'transactionPHIDs' => mpull($xactions, 'getPHID'),
        ));
    }

    if ($this->shouldPublishFeedStory($object, $xactions)) {

      $mailed = array();
      foreach ($messages as $mail) {
        foreach ($mail->buildRecipientList() as $phid) {
          $mailed[$phid] = $phid;
        }
      }

      $this->publishFeedStory($object, $xactions, $mailed);
    }

    // NOTE: This actually sends the mail. We do this last to reduce the chance
    // that we send some mail, hit an exception, then send the mail again when
    // retrying.
    foreach ($messages as $mail) {
      $mail->save();
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
      throw new PhutilInvalidStateException('setContentSource');
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
          pht('You can not apply transactions which already have IDs/PHIDs!'));
      }

      if ($xaction->getObjectPHID()) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'You can not apply transactions which already have %s!',
            'objectPHIDs'));
      }

      if ($xaction->getCommentPHID()) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'You can not apply transactions which already have %s!',
            'commentPHIDs'));
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
            'This transaction is supposed to have an %s set, but it does not!',
            'oldValue'));
      }

      if ($has_value && !$expect_value) {
        throw new PhabricatorApplicationTransactionStructureException(
          $xaction,
          pht(
            'This transaction should generate its %s automatically, '.
            'but has already had one set!',
            'oldValue'));
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
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_SPACE:
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
    array $changes) {

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      return null;
    }

    if ($this->shouldEnableMentions($object, $xactions)) {
      // Identify newly mentioned users. We ignore users who were previously
      // mentioned so that we don't re-subscribe users after an edit of text
      // which mentions them.
      $old_texts = mpull($changes, 'getOldValue');
      $new_texts = mpull($changes, 'getNewValue');

      $old_phids = PhabricatorMarkupEngine::extractPHIDsFromMentions(
        $this->getActor(),
        $old_texts);

      $new_phids = PhabricatorMarkupEngine::extractPHIDsFromMentions(
        $this->getActor(),
        $new_texts);

      $phids = array_diff($new_phids, $old_phids);
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
  protected function expandTransactions(
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

    $changes = $this->getRemarkupChanges($xactions);

    $subscribe_xaction = $this->buildSubscribeTransaction(
      $object,
      $xactions,
      $changes);
    if ($subscribe_xaction) {
      $xactions[] = $subscribe_xaction;
    }

    // TODO: For now, this is just a placeholder.
    $engine = PhabricatorMarkupEngine::getEngine('extract');
    $engine->setConfig('viewer', $this->requireActor());

    $block_xactions = $this->expandRemarkupBlockTransactions(
      $object,
      $xactions,
      $changes,
      $engine);

    foreach ($block_xactions as $xaction) {
      $xactions[] = $xaction;
    }

    return $xactions;
  }

  private function getRemarkupChanges(array $xactions) {
    $changes = array();

    foreach ($xactions as $key => $xaction) {
      foreach ($this->getRemarkupChangesFromTransaction($xaction) as $change) {
        $changes[] = $change;
      }
    }

    return $changes;
  }

  private function getRemarkupChangesFromTransaction(
    PhabricatorApplicationTransaction $transaction) {
    return $transaction->getRemarkupChanges();
  }

  private function expandRemarkupBlockTransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    array $changes,
    PhutilMarkupEngine $engine) {

    $block_xactions = $this->expandCustomRemarkupBlockTransactions(
      $object,
      $xactions,
      $changes,
      $engine);

    $mentioned_phids = array();
    if ($this->shouldEnableMentions($object, $xactions)) {
      foreach ($changes as $change) {
        // Here, we don't care about processing only new mentions after an edit
        // because there is no way for an object to ever "unmention" itself on
        // another object, so we can ignore the old value.
        $engine->markupText($change->getNewValue());

        $mentioned_phids += $engine->getTextMetadata(
          PhabricatorObjectRemarkupRule::KEY_MENTIONED_OBJECTS,
          array());
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
    array $changes,
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
          $other_xaction = $result[$other_key];

          // Don't merge transactions with different authors. For example,
          // don't merge Herald transactions and owners transactions.
          if ($other_xaction->getAuthorPHID() != $xaction->getAuthorPHID()) {
            continue;
          }

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
        pht(
          "Invalid '%s' value for PHID transaction. Value should contain only ".
          "keys '%s' (add PHIDs), '%s' (remove PHIDs) and '%s' (set PHIDS).",
          'new',
          '+',
          '-',
          '='));
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
        pht(
          "Invalid '%s' value for Edge transaction. Value should contain only ".
          "keys '%s' (add edges), '%s' (remove edges) and '%s' (set edges).",
          'new',
          '+',
          '-',
          '='));
    }

    $old = $xaction->getOldValue();

    $lists = array($new_set, $new_add, $new_rem);
    foreach ($lists as $list) {
      $this->checkEdgeList($list, $xaction->getMetadataValue('edge:type'));
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

  private function checkEdgeList($list, $edge_type) {
    if (!$list) {
      return;
    }
    foreach ($list as $key => $item) {
      if (phid_get_type($key) === PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
        throw new Exception(
          pht(
            'Edge transactions must have destination PHIDs as in edge '.
            'lists (found key "%s" on transaction of type "%s").',
            $key,
            $edge_type));
      }
      if (!is_array($item) && $item !== $key) {
        throw new Exception(
          pht(
            'Edge transactions must have PHIDs or edge specs as values '.
            '(found value "%s" on transaction of type "%s").',
            $item,
            $edge_type));
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
                'Transaction edge specification contains unexpected key "%s".',
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
          pht(
            "Edge transaction includes edge of type '%s', but ".
            "transaction is of type '%s'. Each edge transaction ".
            "must alter edges of only one type.",
            $this_type,
            $edge_type));
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
      if ($xaction->hasComment()) {
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

    $xtype = $this->getModularTransactionType($type);
    if ($xtype) {
      $errors[] = $xtype->validateTransactions($object, $xactions);
    }

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
      case PhabricatorTransactions::TYPE_SPACE:
        $errors[] = $this->validateSpaceTransactions(
          $object,
          $xactions,
          $type);
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
            pht(
              'The selected %s policy excludes you. Choose a %s policy '.
              'which allows you to %s the object.',
              $capability,
              $capability,
              $capability));
        }
      }
    }

    return $errors;
  }


  private function validateSpaceTransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    $transaction_type) {
    $errors = array();

    $actor = $this->getActor();

    $has_spaces = PhabricatorSpacesNamespaceQuery::getViewerSpacesExist($actor);
    $actor_spaces = PhabricatorSpacesNamespaceQuery::getViewerSpaces($actor);
    $active_spaces = PhabricatorSpacesNamespaceQuery::getViewerActiveSpaces(
      $actor);
    foreach ($xactions as $xaction) {
      $space_phid = $xaction->getNewValue();

      if ($space_phid === null) {
        if (!$has_spaces) {
          // The install doesn't have any spaces, so this is fine.
          continue;
        }

        // The install has some spaces, so every object needs to be put
        // in a valid space.
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $transaction_type,
          pht('Invalid'),
          pht('You must choose a space for this object.'),
          $xaction);
        continue;
      }

      // If the PHID isn't `null`, it needs to be a valid space that the
      // viewer can see.
      if (empty($actor_spaces[$space_phid])) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $transaction_type,
          pht('Invalid'),
          pht(
            'You can not shift this object in the selected space, because '.
            'the space does not exist or you do not have access to it.'),
          $xaction);
      } else if (empty($active_spaces[$space_phid])) {

        // It's OK to edit objects in an archived space, so just move on if
        // we aren't adjusting the value.
        $old_space_phid = $this->getTransactionOldValue($object, $xaction);
        if ($space_phid == $old_space_phid) {
          continue;
        }

        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $transaction_type,
          pht('Archived'),
          pht(
            'You can not shift this object into the selected space, because '.
            'the space is archived. Objects can not be created inside (or '.
            'moved into) archived spaces.'),
          $xaction);
      }
    }

    return $errors;
  }


  protected function adjustObjectForPolicyChecks(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $copy = clone $object;

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_SUBSCRIBERS:
          $clone_xaction = clone $xaction;
          $clone_xaction->setOldValue(array_values($this->subscribers));
          $clone_xaction->setNewValue(
            $this->getPHIDTransactionNewValue(
              $clone_xaction));

          PhabricatorPolicyRule::passTransactionHintToRule(
            $copy,
            new PhabricatorSubscriptionsSubscribersPolicyRule(),
            array_fuse($clone_xaction->getNewValue()));

          break;
        case PhabricatorTransactions::TYPE_SPACE:
          $space_phid = $this->getTransactionNewValue($object, $xaction);
          $copy->setSpacePHID($space_phid);
          break;
      }
    }

    return $copy;
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

  /**
   * Check that text field input isn't longer than a specified length.
   *
   * A text field input is invalid if the length of the input is longer than a
   * specified length. This length can be determined by the space allotted in
   * the database, or given arbitrarily.
   * This method is intended to make implementing @{method:validateTransaction}
   * more convenient:
   *
   *   $overdrawn = $this->validateIsTextFieldTooLong(
   *     $object->getName(),
   *     $xactions,
   *     $field_length);
   *
   * This will return `true` if the net effect of the object and transactions
   * is a field that is too long.
   *
   * @param wild Current field value.
   * @param list<PhabricatorApplicationTransaction> Transactions editing the
   *          field.
   * @param integer for maximum field length.
   * @return bool True if the field will be too long after edits.
   */
  protected function validateIsTextFieldTooLong(
    $field_value,
    array $xactions,
    $length) {

    if ($xactions) {
      $new_value_length = phutil_utf8_strlen(last($xactions)->getNewValue());
      if ($new_value_length <= $length) {
        return false;
      } else {
        return true;
      }
    }

    $old_value_length = phutil_utf8_strlen($field_value);
    if ($old_value_length <= $length) {
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
  private function buildMail(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $email_to = $this->mailToPHIDs;
    $email_cc = $this->mailCCPHIDs;
    $email_cc = array_merge($email_cc, $this->heraldEmailPHIDs);

    $targets = $this->buildReplyHandler($object)
      ->getMailTargets($email_to, $email_cc);

    // Set this explicitly before we start swapping out the effective actor.
    $this->setActingAsPHID($this->getActingAsPHID());

    $messages = array();
    foreach ($targets as $target) {
      $original_actor = $this->getActor();

      $viewer = $target->getViewer();
      $this->setActor($viewer);
      $locale = PhabricatorEnv::beginScopedLocale($viewer->getTranslation());

      $caught = null;
      $mail = null;
      try {
        // Reload handles for the new viewer.
        $this->loadHandles($xactions);

        $mail = $this->buildMailForTarget($object, $xactions, $target);
      } catch (Exception $ex) {
        $caught = $ex;
      }

      $this->setActor($original_actor);
      unset($locale);

      if ($caught) {
        throw $ex;
      }

      if ($mail) {
        $messages[] = $mail;
      }
    }

    $this->runHeraldMailRules($messages);

    return $messages;
  }

  private function buildMailForTarget(
    PhabricatorLiskDAO $object,
    array $xactions,
    PhabricatorMailTarget $target) {

    // Check if any of the transactions are visible for this viewer. If we
    // don't have any visible transactions, don't send the mail.

    $any_visible = false;
    foreach ($xactions as $xaction) {
      if (!$xaction->shouldHideForMail($xactions)) {
        $any_visible = true;
        break;
      }
    }

    if (!$any_visible) {
      return null;
    }

    $mail = $this->buildMailTemplate($object);
    $body = $this->buildMailBody($object, $xactions);

    $mail_tags = $this->getMailTags($object, $xactions);
    $action = $this->getMailAction($object, $xactions);

    if (PhabricatorEnv::getEnvConfig('metamta.email-preferences')) {
      $this->addEmailPreferenceSectionToMailBody(
        $body,
        $object,
        $xactions);
    }

    $mail
      ->setSensitiveContent(false)
      ->setFrom($this->getActingAsPHID())
      ->setSubjectPrefix($this->getMailSubjectPrefix())
      ->setVarySubjectPrefix('['.$action.']')
      ->setThreadID($this->getMailThreadID($object), $this->getIsNewObject())
      ->setRelatedPHID($object->getPHID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setForceHeraldMailRecipientPHIDs($this->heraldForcedEmailPHIDs)
      ->setMailTags($mail_tags)
      ->setIsBulk(true)
      ->setBody($body->render())
      ->setHTMLBody($body->renderHTML());

    foreach ($body->getAttachments() as $attachment) {
      $mail->addAttachment($attachment);
    }

    if ($this->heraldHeader) {
      $mail->addHeader('X-Herald-Rules', $this->heraldHeader);
    }

    if ($object instanceof PhabricatorProjectInterface) {
      $this->addMailProjectMetadata($object, $mail);
    }

    if ($this->getParentMessageID()) {
      $mail->setParentMessageID($this->getParentMessageID());
    }

    return $target->willSendMail($mail);
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
    throw new Exception(pht('Capability not supported.'));
  }

  /**
   * @task mail
   */
  protected function getMailSubjectPrefix() {
    throw new Exception(pht('Capability not supported.'));
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
    throw new Exception(pht('Capability not supported.'));
  }


  /**
   * @task mail
   */
  protected function getMailTo(PhabricatorLiskDAO $object) {
    throw new Exception(pht('Capability not supported.'));
  }


  /**
   * @task mail
   */
  protected function getMailCC(PhabricatorLiskDAO $object) {
    $phids = array();
    $has_support = false;

    if ($object instanceof PhabricatorSubscribableInterface) {
      $phid = $object->getPHID();
      $phids[] = PhabricatorSubscribersQuery::loadSubscribersForPHID($phid);
      $has_support = true;
    }

    if ($object instanceof PhabricatorProjectInterface) {
      $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $object->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);

      if ($project_phids) {
        $projects = id(new PhabricatorProjectQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs($project_phids)
          ->needWatchers(true)
          ->execute();

        $watcher_phids = array();
        foreach ($projects as $project) {
          foreach ($project->getAllAncestorWatcherPHIDs() as $phid) {
            $watcher_phids[$phid] = $phid;
          }
        }

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
      throw new Exception(pht('Capability not supported.'));
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
  protected function addEmailPreferenceSectionToMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorLiskDAO $object,
    array $xactions) {

    $href = PhabricatorEnv::getProductionURI(
      '/settings/panel/emailpreferences/');
    $body->addLinkSection(pht('EMAIL PREFERENCES'), $href);
  }


  /**
   * @task mail
   */
  protected function addHeadersAndCommentsToMailBody(
    PhabricatorMetaMTAMailBody $body,
    array $xactions,
    $object_label = null,
    $object_href = null) {

    $headers = array();
    $headers_html = array();
    $comments = array();
    $details = array();

    foreach ($xactions as $xaction) {
      if ($xaction->shouldHideForMail($xactions)) {
        continue;
      }

      $header = $xaction->getTitleForMail();
      if ($header !== null) {
        $headers[] = $header;
      }

      $header_html = $xaction->getTitleForHTMLMail();
      if ($header_html !== null) {
        $headers_html[] = $header_html;
      }

      $comment = $xaction->getBodyForMail();
      if ($comment !== null) {
        $comments[] = $comment;
      }

      if ($xaction->hasChangeDetailsForMail()) {
        $details[] = $xaction;
      }
    }

    $headers_text = implode("\n", $headers);
    $body->addRawPlaintextSection($headers_text);

    $headers_html = phutil_implode_html(phutil_tag('br'), $headers_html);

    $header_button = null;
    if ($object_label !== null) {
      $button_style = array(
        'text-decoration: none;',
        'padding: 4px 8px;',
        'margin: 0 8px 8px;',
        'float: right;',
        'color: #464C5C;',
        'font-weight: bold;',
        'border-radius: 3px;',
        'background-color: #F7F7F9;',
        'background-image: linear-gradient(to bottom,#fff,#f1f0f1);',
        'display: inline-block;',
        'border: 1px solid rgba(71,87,120,.2);',
      );

      $header_button = phutil_tag(
        'a',
        array(
          'style' => implode(' ', $button_style),
          'href' => $object_href,
        ),
        $object_label);
    }

    $xactions_style = array(
    );

    $header_action = phutil_tag(
      'td',
      array(),
      $header_button);

    $header_action = phutil_tag(
      'td',
      array(
        'style' => implode(' ', $xactions_style),
      ),
      array(
        $headers_html,
        // Add an extra newline to prevent the "View Object" button from
        // running into the transaction text in Mail.app text snippet
        // previews.
        "\n",
      ));

    $headers_html = phutil_tag(
      'table',
      array(),
      phutil_tag('tr', array(), array($header_action, $header_button)));

    $body->addRawHTMLSection($headers_html);

    foreach ($comments as $comment) {
      $body->addRemarkupSection(null, $comment);
    }

    foreach ($details as $xaction) {
      $details = $xaction->renderChangeDetailsForMail($body->getViewer());
      if ($details !== null) {
        $label = $this->getMailDiffSectionHeader($xaction);
        $body->addHTMLSection($label, $details);
      }
    }

  }

  private function getMailDiffSectionHeader($xaction) {
    $type = $xaction->getTransactionType();

    $xtype = $this->getModularTransactionType($type);
    if ($xtype) {
      return $xtype->getMailDiffSectionHeader();
    }

    return pht('EDIT DETAILS');
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


  /**
   * @task mail
   */
  private function runHeraldMailRules(array $messages) {
    foreach ($messages as $message) {
      $engine = new HeraldEngine();
      $adapter = id(new PhabricatorMailOutboundMailHeraldAdapter())
        ->setObject($message);

      $rules = $engine->loadRulesForAdapter($adapter);
      $effects = $engine->applyRules($rules, $adapter);
      $engine->applyEffects($effects, $adapter, $rules);
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

    $related_phids = $this->feedRelatedPHIDs;
    $subscribed_phids = $this->feedNotifyPHIDs;

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


/* -(  Herald Integration )-------------------------------------------------- */


  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {
    throw new Exception(pht('No herald adapter specified.'));
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

    $adapter = $this->buildHeraldAdapter($object, $xactions)
      ->setContentSource($this->getContentSource())
      ->setIsNewObject($this->getIsNewObject())
      ->setAppliedTransactions($xactions);

    if ($this->getApplicationEmail()) {
      $adapter->setApplicationEmail($this->getApplicationEmail());
    }

    $xscript = HeraldEngine::loadAndApplyRules($adapter);

    $this->setHeraldAdapter($adapter);
    $this->setHeraldTranscript($xscript);

    if ($adapter instanceof HarbormasterBuildableAdapterInterface) {
      HarbormasterBuildable::applyBuildPlans(
        $adapter->getHarbormasterBuildablePHID(),
        $adapter->getHarbormasterContainerPHID(),
        $adapter->getQueuedHarbormasterBuildRequests());
    }

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
        pht(
        "Custom field transaction has no '%s'!",
        'customfield:key'));
    }

    $field = PhabricatorCustomField::getObjectField(
      $object,
      PhabricatorCustomField::ROLE_APPLICATIONTRANSACTIONS,
      $field_key);

    if (!$field) {
      throw new Exception(
        pht(
          "Custom field transaction has invalid '%s'; field '%s' ".
          "is disabled or does not exist.",
          'customfield:key',
          $field_key));
    }

    if (!$field->shouldAppearInApplicationTransactions()) {
      throw new Exception(
        pht(
          "Custom field transaction '%s' does not implement ".
          "integration for %s.",
          $field_key,
          'ApplicationTransactions'));
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

    $changes = $this->getRemarkupChanges($xactions);
    $blocks = mpull($changes, 'getNewValue');

    $phids = array();
    if ($blocks) {
      $phids[] = PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
        $this->getActor(),
        $blocks);
    }

    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();

      $xtype = $this->getModularTransactionType($type);
      if ($xtype) {
        $phids[] = $xtype->extractFilePHIDs($object, $xaction->getNewValue());
      } else {
        $phids[] = $this->extractFilePHIDsFromCustomTransaction(
          $object,
          $xaction);
      }
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

      if ($node instanceof PhabricatorUser) {
        // TODO: At least for now, don't record inverse edge transactions
        // for users (for example, "alincoln joined project X"): Feed fills
        // this role instead.
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


/* -(  Workers  )------------------------------------------------------------ */


  /**
   * Load any object state which is required to publish transactions.
   *
   * This hook is invoked in the main process before we compute data related
   * to publishing transactions (like email "To" and "CC" lists), and again in
   * the worker before publishing occurs.
   *
   * @return object Publishable object.
   * @task workers
   */
  protected function willPublish(PhabricatorLiskDAO $object, array $xactions) {
    return $object;
  }


  /**
   * Convert the editor state to a serializable dictionary which can be passed
   * to a worker.
   *
   * This data will be loaded with @{method:loadWorkerState} in the worker.
   *
   * @return dict<string, wild> Serializable editor state.
   * @task workers
   */
  final private function getWorkerState() {
    $state = array();
    foreach ($this->getAutomaticStateProperties() as $property) {
      $state[$property] = $this->$property;
    }

    $custom_state = $this->getCustomWorkerState();
    $custom_encoding = $this->getCustomWorkerStateEncoding();

    $state += array(
      'excludeMailRecipientPHIDs' => $this->getExcludeMailRecipientPHIDs(),
      'custom' => $this->encodeStateForStorage($custom_state, $custom_encoding),
      'custom.encoding' => $custom_encoding,
    );

    return $state;
  }


  /**
   * Hook; return custom properties which need to be passed to workers.
   *
   * @return dict<string, wild> Custom properties.
   * @task workers
   */
  protected function getCustomWorkerState() {
    return array();
  }


  /**
   * Hook; return storage encoding for custom properties which need to be
   * passed to workers.
   *
   * This primarily allows binary data to be passed to workers and survive
   * JSON encoding.
   *
   * @return dict<string, string> Property encodings.
   * @task workers
   */
  protected function getCustomWorkerStateEncoding() {
    return array();
  }


  /**
   * Load editor state using a dictionary emitted by @{method:getWorkerState}.
   *
   * This method is used to load state when running worker operations.
   *
   * @param dict<string, wild> Editor state, from @{method:getWorkerState}.
   * @return this
   * @task workers
   */
  final public function loadWorkerState(array $state) {
    foreach ($this->getAutomaticStateProperties() as $property) {
      $this->$property = idx($state, $property);
    }

    $exclude = idx($state, 'excludeMailRecipientPHIDs', array());
    $this->setExcludeMailRecipientPHIDs($exclude);

    $custom_state = idx($state, 'custom', array());
    $custom_encodings = idx($state, 'custom.encoding', array());
    $custom = $this->decodeStateFromStorage($custom_state, $custom_encodings);

    $this->loadCustomWorkerState($custom);

    return $this;
  }


  /**
   * Hook; set custom properties on the editor from data emitted by
   * @{method:getCustomWorkerState}.
   *
   * @param dict<string, wild> Custom state,
   *   from @{method:getCustomWorkerState}.
   * @return this
   * @task workers
   */
  protected function loadCustomWorkerState(array $state) {
    return $this;
  }


  /**
   * Get a list of object properties which should be automatically sent to
   * workers in the state data.
   *
   * These properties will be automatically stored and loaded by the editor in
   * the worker.
   *
   * @return list<string> List of properties.
   * @task workers
   */
  private function getAutomaticStateProperties() {
    return array(
      'parentMessageID',
      'disableEmail',
      'isNewObject',
      'heraldEmailPHIDs',
      'heraldForcedEmailPHIDs',
      'heraldHeader',
      'mailToPHIDs',
      'mailCCPHIDs',
      'feedNotifyPHIDs',
      'feedRelatedPHIDs',
    );
  }

  /**
   * Apply encodings prior to storage.
   *
   * See @{method:getCustomWorkerStateEncoding}.
   *
   * @param map<string, wild> Map of values to encode.
   * @param map<string, string> Map of encodings to apply.
   * @return map<string, wild> Map of encoded values.
   * @task workers
   */
  final private function encodeStateForStorage(
    array $state,
    array $encodings) {

    foreach ($state as $key => $value) {
      $encoding = idx($encodings, $key);
      switch ($encoding) {
        case self::STORAGE_ENCODING_BINARY:
          // The mechanics of this encoding (serialize + base64) are a little
          // awkward, but it allows us encode arrays and still be JSON-safe
          // with binary data.

          $value = @serialize($value);
          if ($value === false) {
            throw new Exception(
              pht(
                'Failed to serialize() value for key "%s".',
                $key));
          }

          $value = base64_encode($value);
          if ($value === false) {
            throw new Exception(
              pht(
                'Failed to base64 encode value for key "%s".',
                $key));
          }
          break;
      }
      $state[$key] = $value;
    }

    return $state;
  }


  /**
   * Undo storage encoding applied when storing state.
   *
   * See @{method:getCustomWorkerStateEncoding}.
   *
   * @param map<string, wild> Map of encoded values.
   * @param map<string, string> Map of encodings.
   * @return map<string, wild> Map of decoded values.
   * @task workers
   */
  final private function decodeStateFromStorage(
    array $state,
    array $encodings) {

    foreach ($state as $key => $value) {
      $encoding = idx($encodings, $key);
      switch ($encoding) {
        case self::STORAGE_ENCODING_BINARY:
          $value = base64_decode($value);
          if ($value === false) {
            throw new Exception(
              pht(
                'Failed to base64_decode() value for key "%s".',
                $key));
          }

          $value = unserialize($value);
          break;
      }
      $state[$key] = $value;
    }

    return $state;
  }


  /**
   * Remove conflicts from a list of projects.
   *
   * Objects aren't allowed to be tagged with multiple milestones in the same
   * group, nor projects such that one tag is the ancestor of any other tag.
   * If the list of PHIDs include mutually exclusive projects, remove the
   * conflicting projects.
   *
   * @param list<phid> List of project PHIDs.
   * @return list<phid> List with conflicts removed.
   */
  private function applyProjectConflictRules(array $phids) {
    if (!$phids) {
      return array();
    }

    // Overall, the last project in the list wins in cases of conflict (so when
    // you add something, the thing you just added sticks and removes older
    // values).

    // Beyond that, there are two basic cases:

    // Milestones: An object can't be in "A > Sprint 3" and "A > Sprint 4".
    // If multiple projects are milestones of the same parent, we only keep the
    // last one.

    // Ancestor: You can't be in "A" and "A > B". If "A > B" comes later
    // in the list, we remove "A" and keep "A > B". If "A" comes later, we
    // remove "A > B" and keep "A".

    // Note that it's OK to be in "A > B" and "A > C". There's only a conflict
    // if one project is an ancestor of another. It's OK to have something
    // tagged with multiple projects which share a common ancestor, so long as
    // they are not mutual ancestors.

    $viewer = PhabricatorUser::getOmnipotentUser();

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array_keys($phids))
      ->execute();
    $projects = mpull($projects, null, 'getPHID');

    // We're going to build a map from each project with milestones to the last
    // milestone in the list. This last milestone is the milestone we'll keep.
    $milestone_map = array();

    // We're going to build a set of the projects which have no descendants
    // later in the list. This allows us to apply both ancestor rules.
    $ancestor_map = array();

    foreach ($phids as $phid => $ignored) {
      $project = idx($projects, $phid);
      if (!$project) {
        continue;
      }

      // This is the last milestone we've seen, so set it as the selection for
      // the project's parent. This might be setting a new value or overwriting
      // an earlier value.
      if ($project->isMilestone()) {
        $parent_phid = $project->getParentProjectPHID();
        $milestone_map[$parent_phid] = $phid;
      }

      // Since this is the last item in the list we've examined so far, add it
      // to the set of projects with no later descendants.
      $ancestor_map[$phid] = $phid;

      // Remove any ancestors from the set, since this is a later descendant.
      foreach ($project->getAncestorProjects() as $ancestor) {
        $ancestor_phid = $ancestor->getPHID();
        unset($ancestor_map[$ancestor_phid]);
      }
    }

    // Now that we've built the maps, we can throw away all the projects which
    // have conflicts.
    foreach ($phids as $phid => $ignored) {
      $project = idx($projects, $phid);

      if (!$project) {
        // If a PHID is invalid, we just leave it as-is. We could clean it up,
        // but leaving it untouched is less likely to cause collateral damage.
        continue;
      }

      // If this was a milestone, check if it was the last milestone from its
      // group in the list. If not, remove it from the list.
      if ($project->isMilestone()) {
        $parent_phid = $project->getParentProjectPHID();
        if ($milestone_map[$parent_phid] !== $phid) {
          unset($phids[$phid]);
          continue;
        }
      }

      // If a later project in the list is a subproject of this one, it will
      // have removed ancestors from the map. If this project does not point
      // at itself in the ancestor map, it should be discarded in favor of a
      // subproject that comes later.
      if (idx($ancestor_map, $phid) !== $phid) {
        unset($phids[$phid]);
        continue;
      }

      // If a later project in the list is an ancestor of this one, it will
      // have added itself to the map. If any ancestor of this project points
      // at itself in the map, this project should be dicarded in favor of
      // that later ancestor.
      foreach ($project->getAncestorProjects() as $ancestor) {
        $ancestor_phid = $ancestor->getPHID();
        if (isset($ancestor_map[$ancestor_phid])) {
          unset($phids[$phid]);
          continue 2;
        }
      }
    }

    return $phids;
  }

  /**
   * When the view policy for an object is changed, scramble the secret keys
   * for attached files to invalidate existing URIs.
   */
  private function scrambleFileSecrets($object) {
    // If this is a newly created object, we don't need to scramble anything
    // since it couldn't have been previously published.
    if ($this->getIsNewObject()) {
      return;
    }

    // If the object is a file itself, scramble it.
    if ($object instanceof PhabricatorFile) {
      if ($this->shouldScramblePolicy($object->getViewPolicy())) {
        $object->scrambleSecret();
        $object->save();
      }
    }

    $phid = $object->getPHID();

    $attached_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $phid,
      PhabricatorObjectHasFileEdgeType::EDGECONST);
    if (!$attached_phids) {
      return;
    }

    $omnipotent_viewer = PhabricatorUser::getOmnipotentUser();

    $files = id(new PhabricatorFileQuery())
      ->setViewer($omnipotent_viewer)
      ->withPHIDs($attached_phids)
      ->execute();
    foreach ($files as $file) {
      $view_policy = $file->getViewPolicy();
      if ($this->shouldScramblePolicy($view_policy)) {
        $file->scrambleSecret();
        $file->save();
      }
    }
  }


  /**
   * Check if a policy is strong enough to justify scrambling. Objects which
   * are set to very open policies don't need to scramble their files, and
   * files with very open policies don't need to be scrambled when associated
   * objects change.
   */
  private function shouldScramblePolicy($policy) {
    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
      case PhabricatorPolicies::POLICY_USER:
        return false;
    }

    return true;
  }

  private function updateWorkboardColumns($object, $const, $old, $new) {
    // If an object is removed from a project, remove it from any proxy
    // columns for that project. This allows a task which is moved up from a
    // milestone to the parent to move back into the "Backlog" column on the
    // parent workboard.

    if ($const != PhabricatorProjectObjectHasProjectEdgeType::EDGECONST) {
      return;
    }

    // TODO: This should likely be some future WorkboardInterface.
    $appears_on_workboards = ($object instanceof ManiphestTask);
    if (!$appears_on_workboards) {
      return;
    }

    $removed_phids = array_keys(array_diff_key($old, $new));
    if (!$removed_phids) {
      return;
    }

    // Find any proxy columns for the removed projects.
    $proxy_columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withProxyPHIDs($removed_phids)
      ->execute();
    if (!$proxy_columns) {
      return array();
    }

    $proxy_phids = mpull($proxy_columns, 'getPHID');

    $position_table = new PhabricatorProjectColumnPosition();
    $conn_w = $position_table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE objectPHID = %s AND columnPHID IN (%Ls)',
      $position_table->getTableName(),
      $object->getPHID(),
      $proxy_phids);
  }

  private function getModularTransactionTypes() {
    if ($this->modularTypes === null) {
      $template = $this->object->getApplicationTransactionTemplate();
      if ($template instanceof PhabricatorModularTransaction) {
        $xtypes = $template->newModularTransactionTypes();
        foreach ($xtypes as $key => $xtype) {
          $xtype = clone $xtype;
          $xtype->setEditor($this);
          $xtypes[$key] = $xtype;
        }
      } else {
        $xtypes = array();
      }

      $this->modularTypes = $xtypes;
    }

    return $this->modularTypes;
  }

  private function getModularTransactionType($type) {
    $types = $this->getModularTransactionTypes();
    return idx($types, $type);
  }

  private function willApplyTransactions($object, array $xactions) {
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();

      $xtype = $this->getModularTransactionType($type);
      if (!$xtype) {
        continue;
      }

      $xtype->willApplyTransactions($object, $xactions);
    }
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this object.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created an object: %s.', $author, $object);
  }

}
