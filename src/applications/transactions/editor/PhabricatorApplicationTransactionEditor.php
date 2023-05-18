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
  private $raiseWarnings;
  private $parentMessageID;
  private $heraldAdapter;
  private $heraldTranscript;
  private $subscribers;
  private $unmentionablePHIDMap = array();
  private $transactionGroupID;
  private $applicationEmail;

  private $isPreview;
  private $isHeraldEditor;
  private $isInverseEdgeEditor;
  private $actingAsPHID;

  private $heraldEmailPHIDs = array();
  private $heraldForcedEmailPHIDs = array();
  private $heraldHeader;
  private $mailToPHIDs = array();
  private $mailCCPHIDs = array();
  private $feedNotifyPHIDs = array();
  private $feedRelatedPHIDs = array();
  private $feedShouldPublish = false;
  private $mailShouldSend = false;
  private $modularTypes;
  private $silent;
  private $mustEncrypt = array();
  private $stampTemplates = array();
  private $mailStamps = array();
  private $oldTo = array();
  private $oldCC = array();
  private $mailRemovedPHIDs = array();
  private $mailUnexpandablePHIDs = array();
  private $mailMutedPHIDs = array();
  private $webhookMap = array();

  private $transactionQueue = array();
  private $sendHistory = false;
  private $shouldRequireMFA = false;
  private $hasRequiredMFA = false;
  private $request;
  private $cancelURI;
  private $extensions;

  private $parentEditor;
  private $subEditors = array();
  private $publishableObject;
  private $publishableTransactions;

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

  public function getMentionedPHIDs() {
    return $this->mentionedPHIDs;
  }

  public function setIsPreview($is_preview) {
    $this->isPreview = $is_preview;
    return $this;
  }

  public function getIsPreview() {
    return $this->isPreview;
  }

  public function setIsSilent($silent) {
    $this->silent = $silent;
    return $this;
  }

  public function getIsSilent() {
    return $this->silent;
  }

  public function getMustEncrypt() {
    return $this->mustEncrypt;
  }

  public function getHeraldRuleMonograms() {
    // Convert the stored "<123>, <456>" string into a list: "H123", "H456".
    $list = phutil_string_cast($this->heraldHeader);
    $list = preg_split('/[, ]+/', $list);

    foreach ($list as $key => $item) {
      $item = trim($item, '<>');

      if (!is_numeric($item)) {
        unset($list[$key]);
        continue;
      }

      $list[$key] = 'H'.$item;
    }

    return $list;
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

  public function addUnmentionablePHIDs(array $phids) {
    foreach ($phids as $phid) {
      $this->unmentionablePHIDMap[$phid] = true;
    }
    return $this;
  }

  private function getUnmentionablePHIDMap() {
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

  public function setRaiseWarnings($raise_warnings) {
    $this->raiseWarnings = $raise_warnings;
    return $this;
  }

  public function getRaiseWarnings() {
    return $this->raiseWarnings;
  }

  public function setShouldRequireMFA($should_require_mfa) {
    if ($this->hasRequiredMFA) {
      throw new Exception(
        pht(
          'Call to setShouldRequireMFA() is too late: this Editor has already '.
          'checked for MFA requirements.'));
    }

    $this->shouldRequireMFA = $should_require_mfa;
    return $this;
  }

  public function getShouldRequireMFA() {
    return $this->shouldRequireMFA;
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
    $types[] = PhabricatorTransactions::TYPE_HISTORY;

    $types[] = PhabricatorTransactions::TYPE_FILE;

    if ($this->object instanceof PhabricatorEditEngineSubtypeInterface) {
      $types[] = PhabricatorTransactions::TYPE_SUBTYPE;
    }

    if ($this->object instanceof PhabricatorSubscribableInterface) {
      $types[] = PhabricatorTransactions::TYPE_SUBSCRIBERS;
    }

    if ($this->object instanceof PhabricatorCustomFieldInterface) {
      $types[] = PhabricatorTransactions::TYPE_CUSTOMFIELD;
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

    $types[] = PhabricatorTransactions::TYPE_MFA;

    $template = $this->object->getApplicationTransactionTemplate();
    if ($template instanceof PhabricatorModularTransaction) {
      $xtypes = $template->newModularTransactionTypes();
      foreach ($xtypes as $xtype) {
        $types[] = $xtype->getTransactionTypeConstant();
      }
    }

    if ($template) {
      $comment = $template->getApplicationTransactionCommentObject();
      if ($comment) {
        $types[] = PhabricatorTransactions::TYPE_COMMENT;
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

    // Apply an optional transformation to convert "external" tranaction
    // values (provided by APIs) into "internal" values.

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $type = $xaction->getTransactionType();
    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      $xtype = clone $xtype;
      $xtype->setStorage($xaction);


      // TODO: Provide a modular hook for modern transactions to do a
      // transformation.
      list($old, $new) = array($old, $new);

      return;
    } else {
      switch ($type) {
        case PhabricatorTransactions::TYPE_FILE:
          list($old, $new) = $this->newFileTransactionInternalValues(
            $object,
            $xaction,
            $old,
            $new);
          break;
      }
    }

    $xaction->setOldValue($old);
    $xaction->setNewValue($new);
  }

  private function newFileTransactionInternalValues(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction,
    $old,
    $new) {

    $old_map = array();

    if (!$this->getIsNewObject()) {
      $phid = $object->getPHID();

      $attachment_table = new PhabricatorFileAttachment();
      $attachment_conn = $attachment_table->establishConnection('w');

      $rows = queryfx_all(
        $attachment_conn,
        'SELECT filePHID, attachmentMode FROM %R WHERE objectPHID = %s',
        $attachment_table,
        $phid);
      $old_map = ipull($rows, 'attachmentMode', 'filePHID');
    }

    $mode_ref = PhabricatorFileAttachment::MODE_REFERENCE;
    $mode_detach = PhabricatorFileAttachment::MODE_DETACH;

    $new_map = $old_map;

    foreach ($new as $file_phid => $attachment_mode) {
      $is_ref = ($attachment_mode === $mode_ref);
      $is_detach = ($attachment_mode === $mode_detach);

      if ($is_detach) {
        unset($new_map[$file_phid]);
        continue;
      }

      $old_mode = idx($old_map, $file_phid);

      // If we're adding a reference to a file but it is already attached,
      // don't touch it.

      if ($is_ref) {
        if ($old_mode !== null) {
          continue;
        }
      }

      $new_map[$file_phid] = $attachment_mode;
    }

    foreach (array_keys($old_map + $new_map) as $key) {
      if (isset($old_map[$key]) && isset($new_map[$key])) {
        if ($old_map[$key] === $new_map[$key]) {
          unset($old_map[$key]);
          unset($new_map[$key]);
        }
      }
    }

    return array($old_map, $new_map);
  }

  private function getTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();

    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      $xtype = clone $xtype;
      $xtype->setStorage($xaction);
      return $xtype->generateOldValue($object);
    }

    switch ($type) {
      case PhabricatorTransactions::TYPE_CREATE:
      case PhabricatorTransactions::TYPE_HISTORY:
        return null;
      case PhabricatorTransactions::TYPE_SUBTYPE:
        return $object->getEditEngineSubtype();
      case PhabricatorTransactions::TYPE_MFA:
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
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getInteractPolicy();
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

        // See T13082. If this is an inverse edit, the parent editor has
        // already populated the transaction values correctly.
        if ($this->getIsInverseEdgeEditor()) {
          return $xaction->getOldValue();
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
      case PhabricatorTransactions::TYPE_FILE:
        return null;
      default:
        return $this->getCustomTransactionOldValue($object, $xaction);
    }
  }

  private function getTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();

    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      $xtype = clone $xtype;
      $xtype->setStorage($xaction);
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
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_INLINESTATE:
      case PhabricatorTransactions::TYPE_SUBTYPE:
      case PhabricatorTransactions::TYPE_HISTORY:
      case PhabricatorTransactions::TYPE_FILE:
        return $xaction->getNewValue();
      case PhabricatorTransactions::TYPE_MFA:
        return true;
      case PhabricatorTransactions::TYPE_SPACE:
        $space_phid = $xaction->getNewValue();
        if ($space_phid === null || !strlen($space_phid)) {
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
        // See T13082. If this is an inverse edit, the parent editor has
        // already populated appropriate transaction values.
        if ($this->getIsInverseEdgeEditor()) {
          return $xaction->getNewValue();
        }

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
      case PhabricatorTransactions::TYPE_HISTORY:
        return true;
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
    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      return $xtype->getTransactionHasEffect(
        $object,
        $xaction->getOldValue(),
        $xaction->getNewValue());
    }

    if ($xaction->hasComment()) {
      return true;
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

    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      $xtype = clone $xtype;
      $xtype->setStorage($xaction);
      return $xtype->applyInternalEffects($object, $xaction->getNewValue());
    }

    switch ($type) {
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getCustomFieldForTransaction($object, $xaction);
        return $field->applyApplicationTransactionInternalEffects($xaction);
      case PhabricatorTransactions::TYPE_CREATE:
      case PhabricatorTransactions::TYPE_HISTORY:
      case PhabricatorTransactions::TYPE_SUBTYPE:
      case PhabricatorTransactions::TYPE_MFA:
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_INLINESTATE:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_SPACE:
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_FILE:
        return $this->applyBuiltinInternalTransaction($object, $xaction);
    }

    return $this->applyCustomInternalTransaction($object, $xaction);
  }

  private function applyExternalEffects(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();

    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      $xtype = clone $xtype;
      $xtype->setStorage($xaction);
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
      case PhabricatorTransactions::TYPE_HISTORY:
      case PhabricatorTransactions::TYPE_SUBTYPE:
      case PhabricatorTransactions::TYPE_MFA:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
      case PhabricatorTransactions::TYPE_INLINESTATE:
      case PhabricatorTransactions::TYPE_SPACE:
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_FILE:
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
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        $object->setInteractPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_SPACE:
        $object->setSpacePHID($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_SUBTYPE:
        $object->setEditEngineSubtype($xaction->getNewValue());
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
      case PhabricatorTransactions::TYPE_HISTORY:
        $this->sendHistory = true;
        break;
      case PhabricatorTransactions::TYPE_FILE:
        $this->applyFileTransaction($object, $xaction);
        break;
    }
  }

  private function applyFileTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old_map = $xaction->getOldValue();
    $new_map = $xaction->getNewValue();

    $add_phids = array();
    $rem_phids = array();

    foreach ($new_map as $phid => $mode) {
      $add_phids[$phid] = $mode;
    }

    foreach ($old_map as $phid => $mode) {
      if (!isset($new_map[$phid])) {
        $rem_phids[] = $phid;
      }
    }

    $now = PhabricatorTime::getNow();
    $object_phid = $object->getPHID();
    $attacher_phid = $this->getActingAsPHID();

    $attachment_table = new PhabricatorFileAttachment();
    $attachment_conn = $attachment_table->establishConnection('w');

    $add_sql = array();
    foreach ($add_phids as $add_phid => $add_mode) {
      $add_sql[] = qsprintf(
        $attachment_conn,
        '(%s, %s, %s, %ns, %d, %d)',
        $object_phid,
        $add_phid,
        $add_mode,
        $attacher_phid,
        $now,
        $now);
    }

    $rem_sql = array();
    foreach ($rem_phids as $rem_phid) {
      $rem_sql[] = qsprintf(
        $attachment_conn,
        '%s',
        $rem_phid);
    }

    foreach (PhabricatorLiskDAO::chunkSQL($add_sql) as $chunk) {
      queryfx(
        $attachment_conn,
        'INSERT INTO %R (objectPHID, filePHID, attachmentMode,
            attacherPHID, dateCreated, dateModified)
          VALUES %LQ
          ON DUPLICATE KEY UPDATE
            attachmentMode = VALUES(attachmentMode),
            attacherPHID = VALUES(attacherPHID),
            dateModified = VALUES(dateModified)',
        $attachment_table,
        $chunk);
    }

    foreach (PhabricatorLiskDAO::chunkSQL($rem_sql) as $chunk) {
      queryfx(
        $attachment_conn,
        'DELETE FROM %R WHERE objectPHID = %s AND filePHID in (%LQ)',
        $attachment_table,
        $object_phid,
        $chunk);
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

    if ($this->getIsSilent()) {
      $xaction->setIsSilentTransaction(true);
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

  final protected function didCommitTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();

      // See T13082. When we're writing edges that imply corresponding inverse
      // transactions, apply those inverse transactions now. We have to wait
      // until the object we're editing (with this editor) has committed its
      // transactions to do this. If we don't, the inverse editor may race,
      // build a mail before we actually commit this object, and render "alice
      // added an edge: Unknown Object".

      if ($type === PhabricatorTransactions::TYPE_EDGE) {
        // Don't do anything if we're already an inverse edge editor.
        if ($this->getIsInverseEdgeEditor()) {
          continue;
        }

        $edge_const = $xaction->getMetadataValue('edge:type');
        $edge_type = PhabricatorEdgeType::getByConstant($edge_const);
        if ($edge_type->shouldWriteInverseTransactions()) {
          $this->applyInverseEdgeTransactions(
            $object,
            $xaction,
            $edge_type->getInverseEdgeConstant());
        }
        continue;
      }

      $xtype = $this->getModularTransactionType($object, $type);
      if (!$xtype) {
        continue;
      }

      $xtype = clone $xtype;
      $xtype->setStorage($xaction);
      $xtype->didCommitTransaction($object, $xaction->getNewValue());
    }
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function setContentSourceFromRequest(AphrontRequest $request) {
    $this->setRequest($request);
    return $this->setContentSource(
      PhabricatorContentSource::newFromRequest($request));
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function getCancelURI() {
    return $this->cancelURI;
  }

  protected function getTransactionGroupID() {
    if ($this->transactionGroupID === null) {
      $this->transactionGroupID = Filesystem::readRandomCharacters(32);
    }

    return $this->transactionGroupID;
  }

  final public function applyTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $is_new = ($object->getID() === null);
    $this->isNewObject = $is_new;

    $is_preview = $this->getIsPreview();
    $read_locking = false;
    $transaction_open = false;

    // If we're attempting to apply transactions, lock and reload the object
    // before we go anywhere. If we don't do this at the very beginning, we
    // may be looking at an older version of the object when we populate and
    // filter the transactions. See PHI1165 for an example.

    if (!$is_preview) {
      if (!$is_new) {
        $this->buildOldRecipientLists($object, $xactions);

        $object->openTransaction();
        $transaction_open = true;

        $object->beginReadLocking();
        $read_locking = true;

        $object->reload();
      }
    }

    try {
      $this->object = $object;
      $this->xactions = $xactions;

      $this->validateEditParameters($object, $xactions);
      $xactions = $this->newMFATransactions($object, $xactions);

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

      if (!$is_preview) {
        $errors = array();
        $type_map = mgroup($xactions, 'getTransactionType');
        foreach ($this->getTransactionTypes() as $type) {
          $type_xactions = idx($type_map, $type, array());
          $errors[] = $this->validateTransaction(
            $object,
            $type,
            $type_xactions);
        }

        $errors[] = $this->validateAllTransactions($object, $xactions);
        $errors[] = $this->validateTransactionsWithExtensions(
          $object,
          $xactions);
        $errors = array_mergev($errors);

        $continue_on_missing = $this->getContinueOnMissingFields();
        foreach ($errors as $key => $error) {
          if ($continue_on_missing && $error->getIsMissingFieldError()) {
            unset($errors[$key]);
          }
        }

        if ($errors) {
          throw new PhabricatorApplicationTransactionValidationException(
            $errors);
        }

        if ($this->raiseWarnings) {
          $warnings = array();
          foreach ($xactions as $xaction) {
            if ($this->hasWarnings($object, $xaction)) {
              $warnings[] = $xaction;
            }
          }
          if ($warnings) {
            throw new PhabricatorApplicationTransactionWarningException(
              $warnings);
          }
        }
      }

      foreach ($xactions as $xaction) {
        $this->adjustTransactionValues($object, $xaction);
      }

      // Now that we've merged and combined transactions, check for required
      // capabilities. Note that we're doing this before filtering
      // transactions: if you try to apply an edit which you do not have
      // permission to apply, we want to give you a permissions error even
      // if the edit would have no effect.
      $this->applyCapabilityChecks($object, $xactions);

      $xactions = $this->filterTransactions($object, $xactions);

      if (!$is_preview) {
        $this->hasRequiredMFA = true;
        if ($this->getShouldRequireMFA()) {
          $this->requireMFA($object, $xactions);
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

      $xactions = $this->sortTransactions($xactions);

      if ($is_preview) {
        $this->loadHandles($xactions);
        return $xactions;
      }

      $comment_editor = id(new PhabricatorApplicationTransactionCommentEditor())
        ->setActor($actor)
        ->setActingAsPHID($this->getActingAsPHID())
        ->setContentSource($this->getContentSource())
        ->setIsNewComment(true);

      if (!$transaction_open) {
        $object->openTransaction();
        $transaction_open = true;
      }

      // We can technically test any object for CAN_INTERACT, but we can
      // run into some issues in doing so (for example, in project unit tests).
      // For now, only test for CAN_INTERACT if the object is explicitly a
      // lockable object.

      $was_locked = false;
      if ($object instanceof PhabricatorEditEngineLockableInterface) {
        $was_locked = !PhabricatorPolicyFilter::canInteract($actor, $object);
      }

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

      $group_id = $this->getTransactionGroupID();

      foreach ($xactions as $xaction) {
        if ($was_locked) {
          $is_override = $this->isLockOverrideTransaction($xaction);
          if ($is_override) {
            $xaction->setIsLockOverrideTransaction(true);
          }
        }

        $xaction->setObjectPHID($object->getPHID());
        $xaction->setTransactionGroupID($group_id);

        if ($xaction->getComment()) {
          $xaction->setPHID($xaction->generatePHID());
          $comment_editor->applyEdit($xaction, $xaction->getComment());
        } else {

          // TODO: This is a transitional hack to let us migrate edge
          // transactions to a more efficient storage format. For now, we're
          // going to write a new slim format to the database but keep the old
          // bulky format on the objects so we don't have to upgrade all the
          // edit logic to the new format yet. See T13051.

          $edge_type = PhabricatorTransactions::TYPE_EDGE;
          if ($xaction->getTransactionType() == $edge_type) {
            $bulky_old = $xaction->getOldValue();
            $bulky_new = $xaction->getNewValue();

            $record = PhabricatorEdgeChangeRecord::newFromTransaction($xaction);
            $slim_old = $record->getModernOldEdgeTransactionData();
            $slim_new = $record->getModernNewEdgeTransactionData();

            $xaction->setOldValue($slim_old);
            $xaction->setNewValue($slim_new);
            $xaction->save();

            $xaction->setOldValue($bulky_old);
            $xaction->setNewValue($bulky_new);
          } else {
            $xaction->save();
          }
        }
      }

      foreach ($xactions as $xaction) {
        $this->applyExternalEffects($object, $xaction);
      }

      $xactions = $this->applyFinalEffects($object, $xactions);

      if ($read_locking) {
        $object->endReadLocking();
        $read_locking = false;
      }

      if ($transaction_open) {
        $object->saveTransaction();
        $transaction_open = false;
      }

      $this->didCommitTransactions($object, $xactions);

    } catch (Exception $ex) {
      if ($read_locking) {
        $object->endReadLocking();
        $read_locking = false;
      }

      if ($transaction_open) {
        $object->killTransaction();
        $transaction_open = false;
      }

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
      // Do not run Herald if we're just recording that this object was
      // mentioned elsewhere. This tends to create Herald side effects which
      // feel arbitrary, and can really slow down edits which mention a large
      // number of other objects. See T13114.
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

        $herald_editor = $this->newEditorCopy()
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true)
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
      $this->webhookMap = $adapter->getWebhookMap();
    }

    $xactions = $this->didApplyTransactions($object, $xactions);

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

    // See PHI1134. If we're a subeditor, we don't publish information about
    // the edit yet. Our parent editor still needs to finish applying
    // transactions and execute Herald, which may change the information we
    // publish.

    // For example, Herald actions may change the parent object's title or
    // visibility, or Herald may apply rules like "Must Encrypt" that affect
    // email.

    // Once the parent finishes work, it will queue its own publish step and
    // then queue publish steps for its children.

    $this->publishableObject = $object;
    $this->publishableTransactions = $xactions;
    if (!$this->parentEditor) {
      $this->queuePublishing();
    }

    return $xactions;
  }

  private function queuePublishing() {
    $object = $this->publishableObject;
    $xactions = $this->publishableTransactions;

    if (!$object) {
      throw new Exception(
        pht(
          'Editor method "queuePublishing()" was called, but no publishable '.
          'object is present. This Editor is not ready to publish.'));
    }

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

    if (!$this->getIsSilent()) {
      if ($this->shouldSendMail($object, $xactions)) {
        $this->mailShouldSend = true;
        $this->mailToPHIDs = $this->getMailTo($object);
        $this->mailCCPHIDs = $this->getMailCC($object);
        $this->mailUnexpandablePHIDs = $this->newMailUnexpandablePHIDs($object);

        // Add any recipients who were previously on the notification list
        // but were removed by this change.
        $this->applyOldRecipientLists();

        if ($object instanceof PhabricatorSubscribableInterface) {
          $this->mailMutedPHIDs = PhabricatorEdgeQuery::loadDestinationPHIDs(
            $object->getPHID(),
            PhabricatorMutedByEdgeType::EDGECONST);
        } else {
          $this->mailMutedPHIDs = array();
        }

        $mail_xactions = $this->getTransactionsForMail($object, $xactions);
        $stamps = $this->newMailStamps($object, $xactions);
        foreach ($stamps as $stamp) {
          $this->mailStamps[] = $stamp->toDictionary();
        }
      }

      if ($this->shouldPublishFeedStory($object, $xactions)) {
        $this->feedShouldPublish = true;
        $this->feedRelatedPHIDs = $this->getFeedRelatedPHIDs(
          $object,
          $xactions);
        $this->feedNotifyPHIDs = $this->getFeedNotifyPHIDs(
          $object,
          $xactions);
      }
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

    foreach ($this->subEditors as $sub_editor) {
      $sub_editor->queuePublishing();
    }

    $this->flushTransactionQueue($object);
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
    if ($this->mailShouldSend) {
      $messages = $this->buildMail($object, $xactions);
    }

    if ($this->supportsSearch()) {
      PhabricatorSearchWorker::queueDocumentForIndexing(
        $object->getPHID(),
        array(
          'transactionPHIDs' => mpull($xactions, 'getPHID'),
        ));
    }

    if ($this->feedShouldPublish) {
      $mailed = array();
      foreach ($messages as $mail) {
        foreach ($mail->buildRecipientList() as $phid) {
          $mailed[$phid] = $phid;
        }
      }

      $this->publishFeedStory($object, $xactions, $mailed);
    }

    if ($this->sendHistory) {
      $history_mail = $this->buildHistoryMail($object);
      if ($history_mail) {
        $messages[] = $history_mail;
      }
    }

    foreach ($this->newAuxiliaryMail($object, $xactions) as $message) {
      $messages[] = $message;
    }

    // NOTE: This actually sends the mail. We do this last to reduce the chance
    // that we send some mail, hit an exception, then send the mail again when
    // retrying.
    foreach ($messages as $mail) {
      $mail->save();
    }

    $this->queueWebhooks($object, $xactions);

    return $xactions;
  }

  protected function didApplyTransactions($object, array $xactions) {
    // Hook for subclasses.
    return $xactions;
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

      // See T13082. In the narrow case of applying inverse edge edits, we
      // expect the old value to be populated.
      if ($this->getIsInverseEdgeEditor()) {
        $expect_value = true;
      }

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

  private function applyCapabilityChecks(
    PhabricatorLiskDAO $object,
    array $xactions) {
    assert_instances_of($xactions, 'PhabricatorApplicationTransaction');

    $can_edit = PhabricatorPolicyCapability::CAN_EDIT;

    if ($this->getIsNewObject()) {
      // If we're creating a new object, we don't need any special capabilities
      // on the object. The actor has already made it through creation checks,
      // and objects which haven't been created yet often can not be
      // meaningfully tested for capabilities anyway.
      $required_capabilities = array();
    } else {
      if (!$xactions && !$this->xactions) {
        // If we aren't doing anything, require CAN_EDIT to improve consistency.
        $required_capabilities = array($can_edit);
      } else {
        $required_capabilities = array();

        foreach ($xactions as $xaction) {
          $type = $xaction->getTransactionType();

          $xtype = $this->getModularTransactionType($object, $type);
          if (!$xtype) {
            $capabilities = $this->getLegacyRequiredCapabilities($xaction);
          } else {
            $capabilities = $xtype->getRequiredCapabilities($object, $xaction);
          }

          // For convenience, we allow flexibility in the return types because
          // it's very unusual that a transaction actually requires multiple
          // capability checks.
          if ($capabilities === null) {
            $capabilities = array();
          } else {
            $capabilities = (array)$capabilities;
          }

          foreach ($capabilities as $capability) {
            $required_capabilities[$capability] = $capability;
          }
        }
      }
    }

    $required_capabilities = array_fuse($required_capabilities);
    $actor = $this->getActor();

    if ($required_capabilities) {
      id(new PhabricatorPolicyFilter())
        ->setViewer($actor)
        ->requireCapabilities($required_capabilities)
        ->raisePolicyExceptions(true)
        ->apply(array($object));
    }
  }

  private function getLegacyRequiredCapabilities(
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorTransactions::TYPE_COMMENT:
        // TODO: Comments technically require CAN_INTERACT, but this is
        // currently somewhat special and handled through EditEngine. For now,
        // don't enforce it here.
        return null;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        // Anyone can subscribe to or unsubscribe from anything they can view,
        // with no other permissions.

        $old = array_fuse($xaction->getOldValue());
        $new = array_fuse($xaction->getNewValue());

        // To remove users other than yourself, you must be able to edit the
        // object.
        $rem = array_diff_key($old, $new);
        foreach ($rem as $phid) {
          if ($phid !== $this->getActingAsPHID()) {
            return PhabricatorPolicyCapability::CAN_EDIT;
          }
        }

        // To add users other than yourself, you must be able to interact.
        // This allows "@mentioning" users to work as long as you can comment
        // on objects.

        // If you can edit, we return that policy instead so that you can
        // override a soft lock and still make edits.

        // TODO: This is a little bit hacky. We really want to be able to say
        // "this requires either interact or edit", but there's currently no
        // way to specify this kind of requirement.

        $can_edit = PhabricatorPolicyFilter::hasCapability(
          $this->getActor(),
          $this->object,
          PhabricatorPolicyCapability::CAN_EDIT);

        $add = array_diff_key($new, $old);
        foreach ($add as $phid) {
          if ($phid !== $this->getActingAsPHID()) {
            if ($can_edit) {
              return PhabricatorPolicyCapability::CAN_EDIT;
            } else {
              return PhabricatorPolicyCapability::CAN_INTERACT;
            }
          }
        }

        return null;
      case PhabricatorTransactions::TYPE_TOKEN:
        // TODO: This technically requires CAN_INTERACT, like comments.
        return null;
      case PhabricatorTransactions::TYPE_HISTORY:
        // This is a special magic transaction which sends you history via
        // email and is only partially supported in the upstream. You don't
        // need any capabilities to apply it.
        return null;
      case PhabricatorTransactions::TYPE_MFA:
        // Signing a transaction group with MFA does not require permissions
        // on its own.
        return null;
      case PhabricatorTransactions::TYPE_FILE:
        return null;
      case PhabricatorTransactions::TYPE_EDGE:
        return $this->getLegacyRequiredEdgeCapabilities($xaction);
      default:
        // For other older (non-modular) transactions, always require exactly
        // CAN_EDIT. Transactions which do not need CAN_EDIT or need additional
        // capabilities must move to ModularTransactions.
        return PhabricatorPolicyCapability::CAN_EDIT;
    }
  }

  private function getLegacyRequiredEdgeCapabilities(
    PhabricatorApplicationTransaction $xaction) {

    // You don't need to have edit permission on an object to mention it or
    // otherwise add a relationship pointing toward it.
    if ($this->getIsInverseEdgeEditor()) {
      return null;
    }

    $edge_type = $xaction->getMetadataValue('edge:type');
    switch ($edge_type) {
      case PhabricatorMutedByEdgeType::EDGECONST:
        // At time of writing, you can only write this edge for yourself, so
        // you don't need permissions. If you can eventually mute an object
        // for other users, this would need to be revisited.
        return null;
      case PhabricatorProjectSilencedEdgeType::EDGECONST:
        // At time of writing, you can only write this edge for yourself, so
        // you don't need permissions. If you can eventually silence project
        // for other users, this would need to be revisited.
        return null;
      case PhabricatorObjectMentionsObjectEdgeType::EDGECONST:
        return null;
      case PhabricatorProjectProjectHasMemberEdgeType::EDGECONST:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();

        $add = array_keys(array_diff_key($new, $old));
        $rem = array_keys(array_diff_key($old, $new));

        $actor_phid = $this->requireActor()->getPHID();

        $is_join = (($add === array($actor_phid)) && !$rem);
        $is_leave = (($rem === array($actor_phid)) && !$add);

        if ($is_join) {
          // You need CAN_JOIN to join a project.
          return PhabricatorPolicyCapability::CAN_JOIN;
        }

        if ($is_leave) {
          $object = $this->object;
          // You usually don't need any capabilities to leave a project...
          if ($object->getIsMembershipLocked()) {
            // ...you must be able to edit to leave locked projects, though.
            return PhabricatorPolicyCapability::CAN_EDIT;
          } else {
            return null;
          }
        }

        // You need CAN_EDIT to change members other than yourself.
        return PhabricatorPolicyCapability::CAN_EDIT;
      case PhabricatorObjectHasWatcherEdgeType::EDGECONST:
        // See PHI1024. Watching a project does not require CAN_EDIT.
        return null;
      default:
        return PhabricatorPolicyCapability::CAN_EDIT;
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
        $user = idx($users, $phid);

        // Don't subscribe invalid users.
        if (!$user) {
          unset($phids[$key]);
          continue;
        }

        // Don't subscribe bots that get mentioned. If users truly intend
        // to subscribe them, they can add them explicitly, but it's generally
        // not useful to subscribe bots to objects.
        if ($user->getIsSystemAgent()) {
          unset($phids[$key]);
          continue;
        }

        // Do not subscribe mentioned users who do not have permission to see
        // the object.
        if ($object instanceof PhabricatorPolicyInterface) {
          $can_view = PhabricatorPolicyFilter::hasCapability(
            $user,
            $object,
            PhabricatorPolicyCapability::CAN_VIEW);
          if (!$can_view) {
            unset($phids[$key]);
            continue;
          }
        }

        // Don't subscribe users who are already automatically subscribed.
        if ($object->isAutomaticallySubscribed($phid)) {
          unset($phids[$key]);
          continue;
        }
      }

      $phids = array_values($phids);
    }

    if (!$phids) {
      return null;
    }

    $xaction = $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
      ->setNewValue(array('+' => $phids));

    return $xaction;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $object = $this->object;
    $type = $u->getTransactionType();

    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      return $xtype->mergeTransactions($object, $u, $v);
    }

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

    $file_xaction = $this->newFileTransaction(
      $object,
      $xactions,
      $changes);
    if ($file_xaction) {
      $xactions[] = $file_xaction;
    }

    return $xactions;
  }


  private function newFileTransaction(
    PhabricatorLiskDAO $object,
    array $xactions,
    array $remarkup_changes) {

    assert_instances_of(
      $remarkup_changes,
      'PhabricatorTransactionRemarkupChange');

    $new_map = array();

    $viewer = $this->getActor();

    $old_blocks = mpull($remarkup_changes, 'getOldValue');
    foreach ($old_blocks as $key => $old_block) {
      $old_blocks[$key] = phutil_string_cast($old_block);
    }

    $new_blocks = mpull($remarkup_changes, 'getNewValue');
    foreach ($new_blocks as $key => $new_block) {
      $new_blocks[$key] = phutil_string_cast($new_block);
    }

    $old_refs = PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
      $viewer,
      $old_blocks);
    $old_refs = array_fuse($old_refs);

    $new_refs = PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
      $viewer,
      $new_blocks);
    $new_refs = array_fuse($new_refs);

    $add_refs = array_diff_key($new_refs, $old_refs);
    foreach ($add_refs as $file_phid) {
      $new_map[$file_phid] = PhabricatorFileAttachment::MODE_REFERENCE;
    }

    foreach ($remarkup_changes as $remarkup_change) {
      $metadata = $remarkup_change->getMetadata();

      $attached_phids = idx($metadata, 'attachedFilePHIDs', array());
      foreach ($attached_phids as $file_phid) {

        // If the blocks don't include a new embedded reference to this file,
        // do not actually attach it. A common way for this to happen is for
        // a user to upload a file, then change their mind and remove the
        // reference. We do not want to attach the file if they decided against
        // referencing it.

        if (!isset($new_map[$file_phid])) {
          continue;
        }

        $new_map[$file_phid] = PhabricatorFileAttachment::MODE_ATTACH;
      }
    }

    $file_phids = $this->extractFilePHIDs($object, $xactions);
    foreach ($file_phids as $file_phid) {
      $new_map[$file_phid] = PhabricatorFileAttachment::MODE_ATTACH;
    }

    if (!$new_map) {
      return null;
    }

    $xaction = $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_FILE)
      ->setMetadataValue('attach.implicit', true)
      ->setNewValue($new_map);

    return $xaction;
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
        if ($change->getNewValue() !== null) {
          $engine->markupText($change->getNewValue());
        }

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

    $unmentionable_map = $this->getUnmentionablePHIDMap();

    $mentionable_phids = array();
    if ($this->shouldEnableMentions($object, $xactions)) {
      foreach ($mentioned_objects as $mentioned_object) {
        if ($mentioned_object instanceof PhabricatorMentionableInterface) {
          $mentioned_phid = $mentioned_object->getPHID();
          if (isset($unmentionable_map[$mentioned_phid])) {
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

  public function mergePHIDOrEdgeTransactions(
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

    return $this->getPHIDList($old, $xaction->getNewValue());
  }

  public function getPHIDList(array $old, array $new) {
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
    $type_mfa = PhabricatorTransactions::TYPE_MFA;

    $no_effect = array();
    $has_comment = false;
    $any_effect = false;

    $meta_xactions = array();
    foreach ($xactions as $key => $xaction) {
      if ($xaction->getTransactionType() === $type_mfa) {
        $meta_xactions[$key] = $xaction;
        continue;
      }

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

    // If every transaction is a meta-transaction applying to the transaction
    // group, these transactions are junk.
    if (count($meta_xactions) == count($xactions)) {
      $no_effect = $xactions;
      $any_effect = false;
    }

    if (!$no_effect) {
      return $xactions;
    }

    // If none of the transactions have an effect, the meta-transactions also
    // have no effect. Add them to the "no effect" list so we get a full set
    // of errors for everything.
    if (!$any_effect && !$has_comment) {
      $no_effect += $meta_xactions;
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

    $xtype = $this->getModularTransactionType($object, $type);
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
      case PhabricatorTransactions::TYPE_SUBTYPE:
        $errors[] = $this->validateSubtypeTransactions(
          $object,
          $xactions,
          $type);
        break;
      case PhabricatorTransactions::TYPE_MFA:
        $errors[] = $this->validateMFATransactions(
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
      case PhabricatorTransactions::TYPE_FILE:
        $errors[] = $this->validateFileTransactions(
          $object,
          $xactions,
          $type);
        break;
    }

    return array_mergev($errors);
  }

  private function validateFileTransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    $transaction_type) {

    $errors = array();

    $mode_map = PhabricatorFileAttachment::getModeList();
    $mode_map = array_fuse($mode_map);

    $file_phids = array();
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (!is_array($new)) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $transaction_type,
          pht('Invalid'),
          pht(
            'File attachment transaction must have a map of files to '.
            'attachment modes, found "%s".',
            phutil_describe_type($new)),
          $xaction);
        continue;
      }

      foreach ($new as $file_phid => $attachment_mode) {
        $file_phids[$file_phid] = $file_phid;

        if (is_string($attachment_mode) && isset($mode_map[$attachment_mode])) {
          continue;
        }

        if (!is_string($attachment_mode)) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $transaction_type,
            pht('Invalid'),
            pht(
              'File attachment mode (for file "%s") is invalid. Expected '.
              'a string, found "%s".',
              $file_phid,
              phutil_describe_type($attachment_mode)),
            $xaction);
        } else {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $transaction_type,
            pht('Invalid'),
            pht(
              'File attachment mode "%s" (for file "%s") is invalid. Valid '.
              'modes are: %s.',
              $attachment_mode,
              $file_phid,
              pht_list($mode_map)),
            $xaction);
        }
      }
    }

    if ($file_phids) {
      $file_map = id(new PhabricatorFileQuery())
        ->setViewer($this->getActor())
        ->withPHIDs($file_phids)
        ->execute();
      $file_map = mpull($file_map, null, 'getPHID');
    } else {
      $file_map = array();
    }

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (!is_array($new)) {
        continue;
      }

      foreach ($new as $file_phid => $attachment_mode) {
        if (isset($file_map[$file_phid])) {
          continue;
        }

        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $transaction_type,
          pht('Invalid'),
          pht(
            'File "%s" is invalid: it could not be loaded, or you do not '.
            'have permission to view it. You must be able to see a file to '.
            'attach it to an object.',
            $file_phid),
          $xaction);
      }
    }

    return $errors;
  }


  public function validatePolicyTransaction(
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

  private function validateSubtypeTransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    $transaction_type) {
    $errors = array();

    $map = $object->newEditEngineSubtypeMap();
    $old = $object->getEditEngineSubtype();
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if ($old == $new) {
        continue;
      }

      if (!$map->isValidSubtype($new)) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $transaction_type,
          pht('Invalid'),
          pht(
            'The subtype "%s" is not a valid subtype.',
            $new),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

  private function validateMFATransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    $transaction_type) {
    $errors = array();

    $factors = id(new PhabricatorAuthFactorConfigQuery())
      ->setViewer($this->getActor())
      ->withUserPHIDs(array($this->getActingAsPHID()))
      ->withFactorProviderStatuses(
        array(
          PhabricatorAuthFactorProviderStatus::STATUS_ACTIVE,
          PhabricatorAuthFactorProviderStatus::STATUS_DEPRECATED,
        ))
      ->execute();

    foreach ($xactions as $xaction) {
      if (!$factors) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $transaction_type,
          pht('No MFA'),
          pht(
            'You do not have any MFA factors attached to your account, so '.
            'you can not sign this transaction group with MFA. Add MFA to '.
            'your account in Settings.'),
          $xaction);
      }
    }

    if ($xactions) {
      $this->setShouldRequireMFA(true);
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
    if (($field_value !== null && strlen($field_value)) && empty($xactions)) {
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

    $actor = $this->getActor();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($actor)
      ->withPHIDs(array($actor_phid))
      ->executeOne();
    if (!$user) {
      return $xactions;
    }

    // When a bot acts (usually via the API), don't automatically subscribe
    // them as a side effect. They can always subscribe explicitly if they
    // want, and bot subscriptions normally just clutter things up since bots
    // usually do not read email.
    if ($user->getIsSystemAgent()) {
      return $xactions;
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

    $unexpandable = $this->mailUnexpandablePHIDs;
    if (!is_array($unexpandable)) {
      $unexpandable = array();
    }

    $messages = $this->buildMailWithRecipients(
      $object,
      $xactions,
      $email_to,
      $email_cc,
      $unexpandable);

    $this->runHeraldMailRules($messages);

    return $messages;
  }

  private function buildMailWithRecipients(
    PhabricatorLiskDAO $object,
    array $xactions,
    array $email_to,
    array $email_cc,
    array $unexpandable) {

    $targets = $this->buildReplyHandler($object)
      ->setUnexpandablePHIDs($unexpandable)
      ->getMailTargets($email_to, $email_cc);

    // Set this explicitly before we start swapping out the effective actor.
    $this->setActingAsPHID($this->getActingAsPHID());

    $xaction_phids = mpull($xactions, 'getPHID');

    $messages = array();
    foreach ($targets as $target) {
      $original_actor = $this->getActor();

      $viewer = $target->getViewer();
      $this->setActor($viewer);
      $locale = PhabricatorEnv::beginScopedLocale($viewer->getTranslation());

      $caught = null;
      $mail = null;
      try {
        // Reload the transactions for the current viewer.
        if ($xaction_phids) {
          $query = PhabricatorApplicationTransactionQuery::newQueryForObject(
            $object);

          $mail_xactions = $query
            ->setViewer($viewer)
            ->withObjectPHIDs(array($object->getPHID()))
            ->withPHIDs($xaction_phids)
            ->execute();

          // Sort the mail transactions in the input order.
          $mail_xactions = mpull($mail_xactions, null, 'getPHID');
          $mail_xactions = array_select_keys($mail_xactions, $xaction_phids);
          $mail_xactions = array_values($mail_xactions);
        } else {
          $mail_xactions = array();
        }

        // Reload handles for the current viewer. This covers older code which
        // emits a list of handle PHIDs upfront.
        $this->loadHandles($mail_xactions);

        $mail = $this->buildMailForTarget($object, $mail_xactions, $target);

        if ($mail) {
          if ($this->mustEncrypt) {
            $mail
              ->setMustEncrypt(true)
              ->setMustEncryptReasons($this->mustEncrypt);
          }
        }
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

    return $messages;
  }

  protected function getTransactionsForMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $xactions;
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

    $mail_xactions = $this->getTransactionsForMail($object, $xactions);

    $mail = $this->buildMailTemplate($object);
    $body = $this->buildMailBody($object, $mail_xactions);

    $mail_tags = $this->getMailTags($object, $mail_xactions);
    $action = $this->getMailAction($object, $mail_xactions);
    $stamps = $this->generateMailStamps($object, $this->mailStamps);

    if (PhabricatorEnv::getEnvConfig('metamta.email-preferences')) {
      $this->addEmailPreferenceSectionToMailBody(
        $body,
        $object,
        $mail_xactions);
    }

    $muted_phids = $this->mailMutedPHIDs;
    if (!is_array($muted_phids)) {
      $muted_phids = array();
    }

    $mail
      ->setSensitiveContent(false)
      ->setFrom($this->getActingAsPHID())
      ->setSubjectPrefix($this->getMailSubjectPrefix())
      ->setVarySubjectPrefix('['.$action.']')
      ->setThreadID($this->getMailThreadID($object), $this->getIsNewObject())
      ->setRelatedPHID($object->getPHID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setMutedPHIDs($muted_phids)
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

    // If we have stamps, attach the raw dictionary version (not the actual
    // objects) to the mail so that debugging tools can see what we used to
    // render the final list.
    if ($this->mailStamps) {
      $mail->setMailStampMetadata($this->mailStamps);
    }

    // If we have rendered stamps, attach them to the mail.
    if ($stamps) {
      $mail->setMailStamps($stamps);
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
    return head(msortv($xactions, 'newActionStrengthSortVector'));
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


  protected function newMailUnexpandablePHIDs(PhabricatorLiskDAO $object) {
    return array();
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
      throw new Exception(
        pht('The object being edited does not implement any standard '.
          'interfaces (like PhabricatorSubscribableInterface) which allow '.
          'CCs to be generated automatically. Override the "getMailCC()" '.
          'method and generate CCs explicitly.'));
    }

    return array_mergev($phids);
  }


  /**
   * @task mail
   */
  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = id(new PhabricatorMetaMTAMailBody())
      ->setViewer($this->requireActor())
      ->setContextObject($object);

    $button_label = $this->getObjectLinkButtonLabelForMail($object);
    $button_uri = $this->getObjectLinkButtonURIForMail($object);

    $this->addHeadersAndCommentsToMailBody(
      $body,
      $xactions,
      $button_label,
      $button_uri);

    $this->addCustomFieldsToMailBody($body, $object, $xactions);

    return $body;
  }

  protected function getObjectLinkButtonLabelForMail(
    PhabricatorLiskDAO $object) {
    return null;
  }

  protected function getObjectLinkButtonURIForMail(
    PhabricatorLiskDAO $object) {

    // Most objects define a "getURI()" method which does what we want, but
    // this isn't formally part of an interface at time of writing. Try to
    // call the method, expecting an exception if it does not exist.

    try {
      $uri = $object->getURI();
      return PhabricatorEnv::getProductionURI($uri);
    } catch (Exception $ex) {
      return null;
    }
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
    $object_uri = null) {

    // First, remove transactions which shouldn't be rendered in mail.
    foreach ($xactions as $key => $xaction) {
      if ($xaction->shouldHideForMail($xactions)) {
        unset($xactions[$key]);
      }
    }

    $headers = array();
    $headers_html = array();
    $comments = array();
    $details = array();

    $seen_comment = false;
    foreach ($xactions as $xaction) {

      // Most mail has zero or one comments. In these cases, we render the
      // "alice added a comment." transaction in the header, like a normal
      // transaction.

      // Some mail, like Differential undraft mail or "!history" mail, may
      // have two or more comments. In these cases, we'll put the first
      // "alice added a comment." transaction in the header normally, but
      // move the other transactions down so they provide context above the
      // actual comment.

      $comment = $this->getBodyForTextMail($xaction);
      if ($comment !== null) {
        $is_comment = true;
        $comments[] = array(
          'xaction' => $xaction,
          'comment' => $comment,
          'initial' => !$seen_comment,
        );
      } else {
        $is_comment = false;
      }

      if (!$is_comment || !$seen_comment) {
        $header = $this->getTitleForTextMail($xaction);
        if ($header !== null) {
          $headers[] = $header;
        }

        $header_html = $this->getTitleForHTMLMail($xaction);
        if ($header_html !== null) {
          $headers_html[] = $header_html;
        }
      }

      if ($xaction->hasChangeDetailsForMail()) {
        $details[] = $xaction;
      }

      if ($is_comment) {
        $seen_comment = true;
      }
    }

    $headers_text = implode("\n", $headers);
    $body->addRawPlaintextSection($headers_text);

    $headers_html = phutil_implode_html(phutil_tag('br'), $headers_html);

    $header_button = null;
    if ($object_label !== null && $object_uri !== null) {
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
          'href' => $object_uri,
        ),
        $object_label);
    }

    $xactions_style = array();

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

    foreach ($comments as $spec) {
      $xaction = $spec['xaction'];
      $comment = $spec['comment'];
      $is_initial = $spec['initial'];

      // If this is not the first comment in the mail, add the header showing
      // who wrote the comment immediately above the comment.
      if (!$is_initial) {
        $header = $this->getTitleForTextMail($xaction);
        if ($header !== null) {
          $body->addRawPlaintextSection($header);
        }

        $header_html = $this->getTitleForHTMLMail($xaction);
        if ($header_html !== null) {
          $body->addRawHTMLSection($header_html);
        }
      }

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
    $object = $this->object;

    $xtype = $this->getModularTransactionType($object, $type);
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

    // If some transactions are forcing notification delivery, add the forced
    // recipients to the notify list.
    $force_list = array();
    foreach ($xactions as $xaction) {
      $force_phids = $xaction->getForceNotifyPHIDs();

      if (!$force_phids) {
        continue;
      }

      foreach ($force_phids as $force_phid) {
        $force_list[] = $force_phid;
      }
    }

    $to_list = $this->getMailTo($object);
    $cc_list = $this->getMailCC($object);

    $full_list = array_merge($force_list, $to_list, $cc_list);
    $full_list = array_fuse($full_list);

    return array_keys($full_list);
  }


  /**
   * @task feed
   */
  protected function getFeedStoryData(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $xactions = msortv($xactions, 'newActionStrengthSortVector');

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

    // Remove transactions which don't publish feed stories or notifications.
    // These never show up anywhere, so we don't need to do anything with them.
    foreach ($xactions as $key => $xaction) {
      if (!$xaction->shouldHideForFeed()) {
        continue;
      }

      if (!$xaction->shouldHideForNotifications()) {
        continue;
      }

      unset($xactions[$key]);
    }

    if (!$xactions) {
      return;
    }

    $related_phids = $this->feedRelatedPHIDs;
    $subscribed_phids = $this->feedNotifyPHIDs;

    // Remove muted users from the subscription list so they don't get
    // notifications, either.
    $muted_phids = $this->mailMutedPHIDs;
    if (!is_array($muted_phids)) {
      $muted_phids = array();
    }
    $subscribed_phids = array_fuse($subscribed_phids);
    foreach ($muted_phids as $muted_phid) {
      unset($subscribed_phids[$muted_phid]);
    }
    $subscribed_phids = array_values($subscribed_phids);

    $story_type = $this->getFeedStoryType();
    $story_data = $this->getFeedStoryData($object, $xactions);

    $unexpandable_phids = $this->mailUnexpandablePHIDs;
    if (!is_array($unexpandable_phids)) {
      $unexpandable_phids = array();
    }

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType($story_type)
      ->setStoryData($story_data)
      ->setStoryTime(time())
      ->setStoryAuthorPHID($this->getActingAsPHID())
      ->setRelatedPHIDs($related_phids)
      ->setPrimaryObjectPHID($object->getPHID())
      ->setSubscribedPHIDs($subscribed_phids)
      ->setUnexpandablePHIDs($unexpandable_phids)
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
      ->setActingAsPHID($this->getActingAsPHID())
      ->setAppliedTransactions($xactions);

    if ($this->getApplicationEmail()) {
      $adapter->setApplicationEmail($this->getApplicationEmail());
    }

    // If this editor is operating in silent mode, tell Herald that we aren't
    // going to send any mail. This allows it to skip "the first time this
    // rule matches, send me an email" rules which would otherwise match even
    // though we aren't going to send any mail.
    if ($this->getIsSilent()) {
      $adapter->setForbiddenAction(
        HeraldMailableState::STATECONST,
        HeraldCoreStateReasons::REASON_SILENT);
    }

    $xscript = HeraldEngine::loadAndApplyRules($adapter);

    $this->setHeraldAdapter($adapter);
    $this->setHeraldTranscript($xscript);

    if ($adapter instanceof HarbormasterBuildableAdapterInterface) {
      $buildable_phid = $adapter->getHarbormasterBuildablePHID();

      HarbormasterBuildable::applyBuildPlans(
        $buildable_phid,
        $adapter->getHarbormasterContainerPHID(),
        $adapter->getQueuedHarbormasterBuildRequests());

      // Whether we queued any builds or not, any automatic buildable for this
      // object is now done preparing builds and can transition into a
      // completed status.
      $buildables = id(new HarbormasterBuildableQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withManualBuildables(false)
        ->withBuildablePHIDs(array($buildable_phid))
        ->execute();
      foreach ($buildables as $buildable) {
        // If this buildable has already moved beyond preparation, we don't
        // need to nudge it again.
        if (!$buildable->isPreparing()) {
          continue;
        }
        $buildable->sendMessage(
          $this->getActor(),
          HarbormasterMessageType::BUILDABLE_BUILD,
          true);
      }
    }

    $this->mustEncrypt = $adapter->getMustEncryptReasons();

    // See PHI1134. Propagate "Must Encrypt" state to sub-editors.
    foreach ($this->subEditors as $sub_editor) {
      $sub_editor->mustEncrypt = $this->mustEncrypt;
    }

    $apply_xactions = $this->didApplyHeraldRules($object, $adapter, $xscript);
    assert_instances_of($apply_xactions, 'PhabricatorApplicationTransaction');

    $queue_xactions = $adapter->getQueuedTransactions();

    return array_merge(
      array_values($apply_xactions),
      array_values($queue_xactions));
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

    $phids = array();

    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();

      $xtype = $this->getModularTransactionType($object, $type);
      if ($xtype) {
        $phids[] = $xtype->extractFilePHIDs($object, $xaction->getNewValue());
      } else {
        $phids[] = $this->extractFilePHIDsFromCustomTransaction(
          $object,
          $xaction);
      }
    }

    $phids = array_unique(array_filter(array_mergev($phids)));

    return $phids;
  }

  /**
   * @task files
   */
  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return array();
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

    $object_phid = $object->getPHID();

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

      $node_phid = $node->getPHID();
      $editor = $node->getApplicationTransactionEditor();
      $template = $node->getApplicationTransactionTemplate();

      // See T13082. We have to build these transactions with synthetic values
      // because we've already applied the actual edit to the edge database
      // table. If we try to apply this transaction naturally, it will no-op
      // itself because it doesn't have any effect.

      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(array($node_phid))
        ->withEdgeTypes(array($inverse_type));

      $edge_query->execute();

      $edge_phids = $edge_query->getDestinationPHIDs();
      $edge_phids = array_fuse($edge_phids);

      $new_phids = $edge_phids;
      $old_phids = $edge_phids;

      if (isset($add[$node_phid])) {
        unset($old_phids[$object_phid]);
      } else {
        $old_phids[$object_phid] = $object_phid;
      }

      $template
        ->setTransactionType($xaction->getTransactionType())
        ->setMetadataValue('edge:type', $inverse_type)
        ->setOldValue($old_phids)
        ->setNewValue($new_phids);

      $editor = $this->newSubEditor($editor)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setIsInverseEdgeEditor(true);

      $editor->applyTransactions($node, array($template));
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
  private function getWorkerState() {
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
      'isNewObject',
      'heraldEmailPHIDs',
      'heraldForcedEmailPHIDs',
      'heraldHeader',
      'mailToPHIDs',
      'mailCCPHIDs',
      'feedNotifyPHIDs',
      'feedRelatedPHIDs',
      'feedShouldPublish',
      'mailShouldSend',
      'mustEncrypt',
      'mailStamps',
      'mailUnexpandablePHIDs',
      'mailMutedPHIDs',
      'webhookMap',
      'silent',
      'sendHistory',
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
  private function encodeStateForStorage(
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
  private function decodeStateFromStorage(
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
      // at itself in the map, this project should be discarded in favor of
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

    $omnipotent_viewer = PhabricatorUser::getOmnipotentUser();

    $files = id(new PhabricatorFileQuery())
      ->setViewer($omnipotent_viewer)
      ->withAttachedObjectPHIDs(array($object->getPHID()))
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

  private function getModularTransactionTypes(
    PhabricatorLiskDAO $object) {

    if ($this->modularTypes === null) {
      $template = $object->getApplicationTransactionTemplate();
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

  private function getModularTransactionType($object, $type) {
    $types = $this->getModularTransactionTypes($object);
    return idx($types, $type);
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this object.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created an object: %s.', $author, $object);
  }

/* -(  Queue  )-------------------------------------------------------------- */

  protected function queueTransaction(
    PhabricatorApplicationTransaction $xaction) {
    $this->transactionQueue[] = $xaction;
    return $this;
  }

  private function flushTransactionQueue($object) {
    if (!$this->transactionQueue) {
      return;
    }

    $xactions = $this->transactionQueue;
    $this->transactionQueue = array();

    $editor = $this->newEditorCopy();

    return $editor->applyTransactions($object, $xactions);
  }

  final protected function newSubEditor(
    PhabricatorApplicationTransactionEditor $template = null) {
    $editor = $this->newEditorCopy($template);

    $editor->parentEditor = $this;
    $this->subEditors[] = $editor;

    return $editor;
  }

  private function newEditorCopy(
    PhabricatorApplicationTransactionEditor $template = null) {
    if ($template === null) {
      $template = newv(get_class($this), array());
    }

    $editor = id(clone $template)
      ->setActor($this->getActor())
      ->setContentSource($this->getContentSource())
      ->setContinueOnNoEffect($this->getContinueOnNoEffect())
      ->setContinueOnMissingFields($this->getContinueOnMissingFields())
      ->setParentMessageID($this->getParentMessageID())
      ->setIsSilent($this->getIsSilent());

    if ($this->actingAsPHID !== null) {
      $editor->setActingAsPHID($this->actingAsPHID);
    }

    $editor->mustEncrypt = $this->mustEncrypt;
    $editor->transactionGroupID = $this->getTransactionGroupID();

    return $editor;
  }


/* -(  Stamps  )------------------------------------------------------------- */


  public function newMailStampTemplates($object) {
    $actor = $this->getActor();

    $templates = array();

    $extensions = $this->newMailExtensions($object);
    foreach ($extensions as $extension) {
      $stamps = $extension->newMailStampTemplates($object);
      foreach ($stamps as $stamp) {
        $key = $stamp->getKey();
        if (isset($templates[$key])) {
          throw new Exception(
            pht(
              'Mail extension ("%s") defines a stamp template with the '.
              'same key ("%s") as another template. Each stamp template '.
              'must have a unique key.',
              get_class($extension),
              $key));
        }

        $stamp->setViewer($actor);

        $templates[$key] = $stamp;
      }
    }

    return $templates;
  }

  final public function getMailStamp($key) {
    if (!isset($this->stampTemplates)) {
      throw new PhutilInvalidStateException('newMailStampTemplates');
    }

    if (!isset($this->stampTemplates[$key])) {
      throw new Exception(
        pht(
          'Editor ("%s") has no mail stamp template with provided key ("%s").',
          get_class($this),
          $key));
    }

    return $this->stampTemplates[$key];
  }

  private function newMailStamps($object, array $xactions) {
    $actor = $this->getActor();

    $this->stampTemplates = $this->newMailStampTemplates($object);

    $extensions = $this->newMailExtensions($object);
    $stamps = array();
    foreach ($extensions as $extension) {
      $extension->newMailStamps($object, $xactions);
    }

    return $this->stampTemplates;
  }

  private function newMailExtensions($object) {
    $actor = $this->getActor();

    $all_extensions = PhabricatorMailEngineExtension::getAllExtensions();

    $extensions = array();
    foreach ($all_extensions as $key => $template) {
      $extension = id(clone $template)
        ->setViewer($actor)
        ->setEditor($this);

      if ($extension->supportsObject($object)) {
        $extensions[$key] = $extension;
      }
    }

    return $extensions;
  }

  protected function newAuxiliaryMail($object, array $xactions) {
    return array();
  }

  private function generateMailStamps($object, $data) {
    if (!$data || !is_array($data)) {
      return null;
    }

    $templates = $this->newMailStampTemplates($object);
    foreach ($data as $spec) {
      if (!is_array($spec)) {
        continue;
      }

      $key = idx($spec, 'key');
      if (!isset($templates[$key])) {
        continue;
      }

      $type = idx($spec, 'type');
      if ($templates[$key]->getStampType() !== $type) {
        continue;
      }

      $value = idx($spec, 'value');
      $templates[$key]->setValueFromDictionary($value);
    }

    $results = array();
    foreach ($templates as $template) {
      $value = $template->getValueForRendering();

      $rendered = $template->renderStamps($value);
      if ($rendered === null) {
        continue;
      }

      $rendered = (array)$rendered;
      foreach ($rendered as $stamp) {
        $results[] = $stamp;
      }
    }

    natcasesort($results);

    return $results;
  }

  public function getRemovedRecipientPHIDs() {
    return $this->mailRemovedPHIDs;
  }

  private function buildOldRecipientLists($object, $xactions) {
    // See T4776. Before we start making any changes, build a list of the old
    // recipients. If a change removes a user from the recipient list for an
    // object we still want to notify the user about that change. This allows
    // them to respond if they didn't want to be removed.

    if (!$this->shouldSendMail($object, $xactions)) {
      return;
    }

    $this->oldTo = $this->getMailTo($object);
    $this->oldCC = $this->getMailCC($object);

    return $this;
  }

  private function applyOldRecipientLists() {
    $actor_phid = $this->getActingAsPHID();

    // If you took yourself off the recipient list (for example, by
    // unsubscribing or resigning) assume that you know what you did and
    // don't need to be notified.

    // If you just moved from "To" to "Cc" (or vice versa), you're still a
    // recipient so we don't need to add you back in.

    $map = array_fuse($this->mailToPHIDs) + array_fuse($this->mailCCPHIDs);

    foreach ($this->oldTo as $phid) {
      if ($phid === $actor_phid) {
        continue;
      }

      if (isset($map[$phid])) {
        continue;
      }

      $this->mailToPHIDs[] = $phid;
      $this->mailRemovedPHIDs[] = $phid;
    }

    foreach ($this->oldCC as $phid) {
      if ($phid === $actor_phid) {
        continue;
      }

      if (isset($map[$phid])) {
        continue;
      }

      $this->mailCCPHIDs[] = $phid;
      $this->mailRemovedPHIDs[] = $phid;
    }

    return $this;
  }

  private function queueWebhooks($object, array $xactions) {
    $hook_viewer = PhabricatorUser::getOmnipotentUser();

    $webhook_map = $this->webhookMap;
    if (!is_array($webhook_map)) {
      $webhook_map = array();
    }

    // Add any "Firehose" hooks to the list of hooks we're going to call.
    $firehose_hooks = id(new HeraldWebhookQuery())
      ->setViewer($hook_viewer)
      ->withStatuses(
        array(
          HeraldWebhook::HOOKSTATUS_FIREHOSE,
        ))
      ->execute();
    foreach ($firehose_hooks as $firehose_hook) {
      // This is "the hook itself is the reason this hook is being called",
      // since we're including it because it's configured as a firehose
      // hook.
      $hook_phid = $firehose_hook->getPHID();
      $webhook_map[$hook_phid][] = $hook_phid;
    }

    if (!$webhook_map) {
      return;
    }

    // NOTE: We're going to queue calls to disabled webhooks, they'll just
    // immediately fail in the worker queue. This makes the behavior more
    // visible.

    $call_hooks = id(new HeraldWebhookQuery())
      ->setViewer($hook_viewer)
      ->withPHIDs(array_keys($webhook_map))
      ->execute();

    foreach ($call_hooks as $call_hook) {
      $trigger_phids = idx($webhook_map, $call_hook->getPHID());

      $request = HeraldWebhookRequest::initializeNewWebhookRequest($call_hook)
        ->setObjectPHID($object->getPHID())
        ->setTransactionPHIDs(mpull($xactions, 'getPHID'))
        ->setTriggerPHIDs($trigger_phids)
        ->setRetryMode(HeraldWebhookRequest::RETRY_FOREVER)
        ->setIsSilentAction((bool)$this->getIsSilent())
        ->setIsSecureAction((bool)$this->getMustEncrypt())
        ->save();

      $request->queueCall();
    }
  }

  private function hasWarnings($object, $xaction) {
    // TODO: For the moment, this is a very un-modular hack to support
    // a small number of warnings related to draft revisions. See PHI433.

    if (!($object instanceof DifferentialRevision)) {
      return false;
    }

    $type = $xaction->getTransactionType();

    // TODO: This doesn't warn for inlines in Audit, even though they have
    // the same overall workflow.
    if ($type === DifferentialTransaction::TYPE_INLINE) {
      return (bool)$xaction->getComment()->getAttribute('editing', false);
    }

    if (!$object->isDraft()) {
      return false;
    }

    if ($type != PhabricatorTransactions::TYPE_SUBSCRIBERS) {
      return false;
    }

    // We're only going to raise a warning if the transaction adds subscribers
    // other than the acting user. (This implementation is clumsy because the
    // code runs before a lot of normalization occurs.)

    $old = $this->getTransactionOldValue($object, $xaction);
    $new = $this->getPHIDTransactionNewValue($xaction, $old);
    $old = array_fuse($old);
    $new = array_fuse($new);
    $add = array_diff_key($new, $old);

    unset($add[$this->getActingAsPHID()]);

    if (!$add) {
      return false;
    }

    return true;
  }

  private function buildHistoryMail(PhabricatorLiskDAO $object) {
    $viewer = $this->requireActor();
    $recipient_phid = $this->getActingAsPHID();

    // Load every transaction so we can build a mail message with a complete
    // history for the object.
    $query = PhabricatorApplicationTransactionQuery::newQueryForObject($object);
    $xactions = $query
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->execute();
    $xactions = array_reverse($xactions);

    $mail_messages = $this->buildMailWithRecipients(
      $object,
      $xactions,
      array($recipient_phid),
      array(),
      array());
    $mail = head($mail_messages);

    // Since the user explicitly requested "!history", force delivery of this
    // message regardless of their other mail settings.
    $mail->setForceDelivery(true);

    return $mail;
  }

  public function newAutomaticInlineTransactions(
    PhabricatorLiskDAO $object,
    $transaction_type,
    PhabricatorCursorPagedPolicyAwareQuery $query_template) {

    $actor = $this->getActor();

    $inlines = id(clone $query_template)
      ->setViewer($actor)
      ->withObjectPHIDs(array($object->getPHID()))
      ->withPublishableComments(true)
      ->needAppliedDrafts(true)
      ->needReplyToComments(true)
      ->execute();
    $inlines = msort($inlines, 'getID');

    $xactions = array();

    foreach ($inlines as $key => $inline) {
      $xactions[] = $object->getApplicationTransactionTemplate()
        ->setTransactionType($transaction_type)
        ->attachComment($inline);
    }

    $state_xaction = $this->newInlineStateTransaction(
      $object,
      $query_template);

    if ($state_xaction) {
      $xactions[] = $state_xaction;
    }

    return $xactions;
  }

  protected function newInlineStateTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorCursorPagedPolicyAwareQuery $query_template) {

    $actor_phid = $this->getActingAsPHID();
    $author_phid = $object->getAuthorPHID();
    $actor_is_author = ($actor_phid == $author_phid);

    $state_map = PhabricatorTransactions::getInlineStateMap();

    $inline_query = id(clone $query_template)
      ->setViewer($this->getActor())
      ->withObjectPHIDs(array($object->getPHID()))
      ->withFixedStates(array_keys($state_map))
      ->withPublishableComments(true);

    if ($actor_is_author) {
      $inline_query->withPublishedComments(true);
    }

    $inlines = $inline_query->execute();

    if (!$inlines) {
      return null;
    }

    $old_value = mpull($inlines, 'getFixedState', 'getPHID');
    $new_value = array();
    foreach ($old_value as $key => $state) {
      $new_value[$key] = $state_map[$state];
    }

    // See PHI995. Copy some information about the inlines into the transaction
    // so we can tailor rendering behavior. In particular, we don't want to
    // render transactions about users marking their own inlines as "Done".

    $inline_details = array();
    foreach ($inlines as $inline) {
      $inline_details[$inline->getPHID()] = array(
        'authorPHID' => $inline->getAuthorPHID(),
      );
    }

    return $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_INLINESTATE)
      ->setIgnoreOnNoEffect(true)
      ->setMetadataValue('inline.details', $inline_details)
      ->setOldValue($old_value)
      ->setNewValue($new_value);
  }

  private function requireMFA(PhabricatorLiskDAO $object, array $xactions) {
    $actor = $this->getActor();

    // Let omnipotent editors skip MFA. This is mostly aimed at scripts.
    if ($actor->isOmnipotent()) {
      return;
    }

    $editor_class = get_class($this);

    $object_phid = $object->getPHID();
    if ($object_phid) {
      $workflow_key = sprintf(
        'editor(%s).phid(%s)',
        $editor_class,
        $object_phid);
    } else {
      $workflow_key = sprintf(
        'editor(%s).new()',
        $editor_class);
    }

    $request = $this->getRequest();
    if ($request === null) {
      $source_type = $this->getContentSource()->getSourceTypeConstant();
      $conduit_type = PhabricatorConduitContentSource::SOURCECONST;
      $is_conduit = ($source_type === $conduit_type);
      if ($is_conduit) {
        throw new Exception(
          pht(
            'This transaction group requires MFA to apply, but you can not '.
            'provide an MFA response via Conduit. Edit this object via the '.
            'web UI.'));
      } else {
        throw new Exception(
          pht(
            'This transaction group requires MFA to apply, but the Editor was '.
            'not configured with a Request. This workflow can not perform an '.
            'MFA check.'));
      }
    }

    $cancel_uri = $this->getCancelURI();
    if ($cancel_uri === null) {
      throw new Exception(
        pht(
          'This transaction group requires MFA to apply, but the Editor was '.
          'not configured with a Cancel URI. This workflow can not perform '.
          'an MFA check.'));
    }

    $token = id(new PhabricatorAuthSessionEngine())
      ->setWorkflowKey($workflow_key)
      ->requireHighSecurityToken($actor, $request, $cancel_uri);

    if (!$token->getIsUnchallengedToken()) {
      foreach ($xactions as $xaction) {
        $xaction->setIsMFATransaction(true);
      }
    }
  }

  private function newMFATransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $has_engine = ($object instanceof PhabricatorEditEngineMFAInterface);
    if ($has_engine) {
      $engine = PhabricatorEditEngineMFAEngine::newEngineForObject($object)
        ->setViewer($this->getActor());
      $require_mfa = $engine->shouldRequireMFA();
      $try_mfa = $engine->shouldTryMFA();
    } else {
      $require_mfa = false;
      $try_mfa = false;
    }

    // If the user is mentioning an MFA object on another object or creating
    // a relationship like "parent" or "child" to this object, we always
    // allow the edit to move forward without requiring MFA.
    if ($this->getIsInverseEdgeEditor()) {
      return $xactions;
    }

    if (!$require_mfa) {
      // If the object hasn't already opted into MFA, see if any of the
      // transactions want it.
      if (!$try_mfa) {
        foreach ($xactions as $xaction) {
          $type = $xaction->getTransactionType();

          $xtype = $this->getModularTransactionType($object, $type);
          if ($xtype) {
            $xtype = clone $xtype;
            $xtype->setStorage($xaction);
            if ($xtype->shouldTryMFA($object, $xaction)) {
              $try_mfa = true;
              break;
            }
          }
        }
      }

      if ($try_mfa) {
        $this->setShouldRequireMFA(true);
      }

      return $xactions;
    }

    $type_mfa = PhabricatorTransactions::TYPE_MFA;

    $has_mfa = false;
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() === $type_mfa) {
        $has_mfa = true;
        break;
      }
    }

    if ($has_mfa) {
      return $xactions;
    }

    $template = $object->getApplicationTransactionTemplate();

    $mfa_xaction = id(clone $template)
      ->setTransactionType($type_mfa)
      ->setNewValue(true);

    array_unshift($xactions, $mfa_xaction);

    return $xactions;
  }

  private function getTitleForTextMail(
    PhabricatorApplicationTransaction $xaction) {
    $type = $xaction->getTransactionType();
    $object = $this->object;

    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      $xtype = clone $xtype;
      $xtype->setStorage($xaction);
      $comment = $xtype->getTitleForTextMail();
      if ($comment !== false) {
        return $comment;
      }
    }

    return $xaction->getTitleForTextMail();
  }

  private function getTitleForHTMLMail(
    PhabricatorApplicationTransaction $xaction) {
    $type = $xaction->getTransactionType();
    $object = $this->object;

    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      $xtype = clone $xtype;
      $xtype->setStorage($xaction);
      $comment = $xtype->getTitleForHTMLMail();
      if ($comment !== false) {
        return $comment;
      }
    }

    return $xaction->getTitleForHTMLMail();
  }


  private function getBodyForTextMail(
    PhabricatorApplicationTransaction $xaction) {
    $type = $xaction->getTransactionType();
    $object = $this->object;

    $xtype = $this->getModularTransactionType($object, $type);
    if ($xtype) {
      $xtype = clone $xtype;
      $xtype->setStorage($xaction);
      $comment = $xtype->getBodyForTextMail();
      if ($comment !== false) {
        return $comment;
      }
    }

    return $xaction->getBodyForMail();
  }

  private function isLockOverrideTransaction(
    PhabricatorApplicationTransaction $xaction) {

    // See PHI1209. When an object is locked, certain types of transactions
    // can still be applied without requiring a policy check, like subscribing
    // or unsubscribing. We don't want these transactions to show the "Lock
    // Override" icon in the transaction timeline.

    // We could test if a transaction did no direct policy checks, but it may
    // have done additional policy checks during validation, so this is not a
    // reliable test (and could cause false negatives, where edits which did
    // override a lock are not marked properly).

    // For now, do this in a narrow way and just check against a hard-coded
    // list of non-override transaction situations. Some day, this should
    // likely be modularized.


    // Inverse edge edits don't interact with locks.
    if ($this->getIsInverseEdgeEditor()) {
      return false;
    }

    // For now, all edits other than subscribes always override locks.
    $type = $xaction->getTransactionType();
    if ($type !== PhabricatorTransactions::TYPE_SUBSCRIBERS) {
      return true;
    }

    // Subscribes override locks if they affect any users other than the
    // acting user.

    $acting_phid = $this->getActingAsPHID();

    $old = array_fuse($xaction->getOldValue());
    $new = array_fuse($xaction->getNewValue());
    $add = array_diff_key($new, $old);
    $rem = array_diff_key($old, $new);

    $all = $add + $rem;
    foreach ($all as $phid) {
      if ($phid !== $acting_phid) {
        return true;
      }
    }

    return false;
  }


/* -(  Extensions  )--------------------------------------------------------- */


  private function validateTransactionsWithExtensions(
    PhabricatorLiskDAO $object,
    array $xactions) {
    $errors = array();

    $extensions = $this->getEditorExtensions();
    foreach ($extensions as $extension) {
      $extension_errors = $extension
        ->setObject($object)
        ->validateTransactions($object, $xactions);

      assert_instances_of(
        $extension_errors,
        'PhabricatorApplicationTransactionValidationError');

      $errors[] = $extension_errors;
    }

    return array_mergev($errors);
  }

  private function getEditorExtensions() {
    if ($this->extensions === null) {
      $this->extensions = $this->newEditorExtensions();
    }
    return $this->extensions;
  }

  private function newEditorExtensions() {
    $extensions = PhabricatorEditorExtension::getAllExtensions();

    $actor = $this->getActor();
    $object = $this->object;
    foreach ($extensions as $key => $extension) {

      $extension = id(clone $extension)
        ->setViewer($actor)
        ->setEditor($this)
        ->setObject($object);

      if (!$extension->supportsObject($this, $object)) {
        unset($extensions[$key]);
        continue;
      }

      $extensions[$key] = $extension;
    }

    return $extensions;
  }


}
