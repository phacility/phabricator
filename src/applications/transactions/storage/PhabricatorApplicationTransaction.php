<?php

abstract class PhabricatorApplicationTransaction
  extends PhabricatorLiskDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  const TARGET_TEXT = 'text';
  const TARGET_HTML = 'html';

  protected $phid;
  protected $objectPHID;
  protected $authorPHID;
  protected $viewPolicy;
  protected $editPolicy;

  protected $commentPHID;
  protected $commentVersion = 0;
  protected $transactionType;
  protected $oldValue;
  protected $newValue;
  protected $metadata = array();

  protected $contentSource;

  private $comment;
  private $commentNotLoaded;

  private $handles;
  private $renderingTarget = self::TARGET_HTML;
  private $transactionGroup = array();
  private $viewer = self::ATTACHABLE;
  private $object = self::ATTACHABLE;
  private $oldValueHasBeenSet = false;

  private $ignoreOnNoEffect;


  /**
   * Flag this transaction as a pure side-effect which should be ignored when
   * applying transactions if it has no effect, even if transaction application
   * would normally fail. This both provides users with better error messages
   * and allows transactions to perform optional side effects.
   */
  public function setIgnoreOnNoEffect($ignore) {
    $this->ignoreOnNoEffect = $ignore;
    return $this;
  }

  public function getIgnoreOnNoEffect() {
    return $this->ignoreOnNoEffect;
  }

  public function shouldGenerateOldValue() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
      case PhabricatorTransactions::TYPE_INLINESTATE:
        return false;
    }
    return true;
  }

  abstract public function getApplicationTransactionType();

  private function getApplicationObjectTypeName() {
    $types = PhabricatorPHIDType::getAllTypes();

    $type = idx($types, $this->getApplicationTransactionType());
    if ($type) {
      return $type->getTypeName();
    }

    return pht('Object');
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getMetadataValue($key, $default = null) {
    return idx($this->metadata, $key, $default);
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function generatePHID() {
    $type = PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST;
    $subtype = $this->getApplicationTransactionType();

    return PhabricatorPHID::generateNewPHID($type, $subtype);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'oldValue' => self::SERIALIZATION_JSON,
        'newValue' => self::SERIALIZATION_JSON,
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'commentPHID' => 'phid?',
        'commentVersion' => 'uint32',
        'contentSource' => 'text',
        'transactionType' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source->serialize();
    return $this;
  }

  public function getContentSource() {
    return PhabricatorContentSource::newFromSerialized($this->contentSource);
  }

  public function hasComment() {
    $comment = $this->getComment();
    if (!$comment) {
      return false;
    }

    if ($comment->isEmptyComment()) {
      return false;
    }

    return true;
  }

  public function getComment() {
    if ($this->commentNotLoaded) {
      throw new Exception(pht('Comment for this transaction was not loaded.'));
    }
    return $this->comment;
  }

  public function setIsCreateTransaction($create) {
    return $this->setMetadataValue('core.create', $create);
  }

  public function getIsCreateTransaction() {
    return (bool)$this->getMetadataValue('core.create', false);
  }

  public function setIsDefaultTransaction($default) {
    return $this->setMetadataValue('core.default', $default);
  }

  public function getIsDefaultTransaction() {
    return (bool)$this->getMetadataValue('core.default', false);
  }

  public function setIsSilentTransaction($silent) {
    return $this->setMetadataValue('core.silent', $silent);
  }

  public function getIsSilentTransaction() {
    return (bool)$this->getMetadataValue('core.silent', false);
  }

  public function setIsMFATransaction($mfa) {
    return $this->setMetadataValue('core.mfa', $mfa);
  }

  public function getIsMFATransaction() {
    return (bool)$this->getMetadataValue('core.mfa', false);
  }

  public function setIsLockOverrideTransaction($override) {
    return $this->setMetadataValue('core.lock-override', $override);
  }

  public function getIsLockOverrideTransaction() {
    return (bool)$this->getMetadataValue('core.lock-override', false);
  }

  public function setTransactionGroupID($group_id) {
    return $this->setMetadataValue('core.groupID', $group_id);
  }

  public function getTransactionGroupID() {
    return $this->getMetadataValue('core.groupID', null);
  }

  public function attachComment(
    PhabricatorApplicationTransactionComment $comment) {
    $this->comment = $comment;
    $this->commentNotLoaded = false;
    return $this;
  }

  public function setCommentNotLoaded($not_loaded) {
    $this->commentNotLoaded = $not_loaded;
    return $this;
  }

  public function attachObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function getRemarkupChanges() {
    $changes = $this->newRemarkupChanges();
    assert_instances_of($changes, 'PhabricatorTransactionRemarkupChange');

    // Convert older-style remarkup blocks into newer-style remarkup changes.
    // This builds changes that do not have the correct "old value", so rules
    // that operate differently against edits (like @user mentions) won't work
    // properly.
    foreach ($this->getRemarkupBlocks() as $block) {
      $changes[] = $this->newRemarkupChange()
        ->setOldValue(null)
        ->setNewValue($block);
    }

    $comment = $this->getComment();
    if ($comment) {
      if ($comment->hasOldComment()) {
        $old_value = $comment->getOldComment()->getContent();
      } else {
        $old_value = null;
      }

      $new_value = $comment->getContent();

      $changes[] = $this->newRemarkupChange()
        ->setOldValue($old_value)
        ->setNewValue($new_value);
    }

    $metadata = $this->getMetadataValue('remarkup.control');

    if (!is_array($metadata)) {
      $metadata = array();
    }

    foreach ($changes as $change) {
      if (!$change->getMetadata()) {
        $change->setMetadata($metadata);
      }
    }

    return $changes;
  }

  protected function newRemarkupChanges() {
    return array();
  }

  protected function newRemarkupChange() {
    return id(new PhabricatorTransactionRemarkupChange())
      ->setTransaction($this);
  }

  /**
   * @deprecated
   */
  public function getRemarkupBlocks() {
    $blocks = array();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          $custom_blocks = $field->getApplicationTransactionRemarkupBlocks(
            $this);
          foreach ($custom_blocks as $custom_block) {
            $blocks[] = $custom_block;
          }
        }
        break;
    }

    return $blocks;
  }

  public function setOldValue($value) {
    $this->oldValueHasBeenSet = true;
    $this->writeField('oldValue', $value);
    return $this;
  }

  public function hasOldValue() {
    return $this->oldValueHasBeenSet;
  }

  public function newChronologicalSortVector() {
    return id(new PhutilSortVector())
      ->addInt((int)$this->getDateCreated())
      ->addInt((int)$this->getID());
  }

/* -(  Rendering  )---------------------------------------------------------- */

  public function setRenderingTarget($rendering_target) {
    $this->renderingTarget = $rendering_target;
    return $this;
  }

  public function getRenderingTarget() {
    return $this->renderingTarget;
  }

  public function attachViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->assertAttached($this->viewer);
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $phids[] = array($this->getAuthorPHID());
    $phids[] = array($this->getObjectPHID());
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          $phids[] = $field->getApplicationTransactionRequiredHandlePHIDs(
            $this);
        }
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $phids[] = $old;
        $phids[] = $new;
        break;
      case PhabricatorTransactions::TYPE_FILE:
        $phids[] = array_keys($old + $new);
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $record = PhabricatorEdgeChangeRecord::newFromTransaction($this);
        $phids[] = $record->getChangedPHIDs();
        break;
      case PhabricatorTransactions::TYPE_COLUMNS:
        foreach ($new as $move) {
          $phids[] = array(
            $move['columnPHID'],
            $move['boardPHID'],
          );
          $phids[] = $move['fromColumnPHIDs'];
        }
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        if (!PhabricatorPolicyQuery::isSpecialPolicy($old)) {
          $phids[] = array($old);
        }
        if (!PhabricatorPolicyQuery::isSpecialPolicy($new)) {
          $phids[] = array($new);
        }
        break;
      case PhabricatorTransactions::TYPE_SPACE:
        if ($old) {
          $phids[] = array($old);
        }
        if ($new) {
          $phids[] = array($new);
        }
        break;
      case PhabricatorTransactions::TYPE_TOKEN:
        break;
    }

    if ($this->getComment()) {
      $phids[] = array($this->getComment()->getAuthorPHID());
    }

    return array_mergev($phids);
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getHandle($phid) {
    if (empty($this->handles[$phid])) {
      throw new Exception(
        pht(
          'Transaction ("%s", of type "%s") requires a handle ("%s") that it '.
          'did not load.',
          $this->getPHID(),
          $this->getTransactionType(),
          $phid));
    }
    return $this->handles[$phid];
  }

  public function getHandleIfExists($phid) {
    return idx($this->handles, $phid);
  }

  public function getHandles() {
    if ($this->handles === null) {
      throw new Exception(
        pht('Transaction requires handles and it did not load them.'));
    }
    return $this->handles;
  }

  public function renderHandleLink($phid) {
    if ($this->renderingTarget == self::TARGET_HTML) {
      return $this->getHandle($phid)->renderHovercardLink();
    } else {
      return $this->getHandle($phid)->getLinkName();
    }
  }

  public function renderHandleList(array $phids) {
    $links = array();
    foreach ($phids as $phid) {
      $links[] = $this->renderHandleLink($phid);
    }
    if ($this->renderingTarget == self::TARGET_HTML) {
      return phutil_implode_html(', ', $links);
    } else {
      return implode(', ', $links);
    }
  }

  private function renderSubscriberList(array $phids, $change_type) {
    if ($this->getRenderingTarget() == self::TARGET_TEXT) {
      return $this->renderHandleList($phids);
    } else {
      $handles = array_select_keys($this->getHandles(), $phids);
      return id(new SubscriptionListStringBuilder())
        ->setHandles($handles)
        ->setObjectPHID($this->getPHID())
        ->buildTransactionString($change_type);
    }
  }

  protected function renderPolicyName($phid, $state = 'old') {
    $policy = PhabricatorPolicy::newFromPolicyAndHandle(
      $phid,
      $this->getHandleIfExists($phid));

    $ref = $policy->newRef($this->getViewer());

    if ($this->renderingTarget == self::TARGET_HTML) {
      $output = $ref->newTransactionLink($state, $this);
    } else {
      $output = $ref->getPolicyDisplayName();
    }

    return $output;
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $comment = $this->getComment();
        if ($comment && $comment->getIsRemoved()) {
          return 'fa-trash';
        }
        return 'fa-comment';
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $old = $this->getOldValue();
        $new = $this->getNewValue();
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        if ($add && $rem) {
          return 'fa-user';
        } else if ($add) {
          return 'fa-user-plus';
        } else if ($rem) {
          return 'fa-user-times';
        } else {
          return 'fa-user';
        }
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        return 'fa-lock';
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($this->getMetadataValue('edge:type')) {
          case DiffusionCommitRevertedByCommitEdgeType::EDGECONST:
            return 'fa-undo';
          case DiffusionCommitRevertsCommitEdgeType::EDGECONST:
            return 'fa-ambulance';
        }
        return 'fa-link';
      case PhabricatorTransactions::TYPE_TOKEN:
        return 'fa-trophy';
      case PhabricatorTransactions::TYPE_SPACE:
        return 'fa-th-large';
      case PhabricatorTransactions::TYPE_COLUMNS:
        return 'fa-columns';
      case PhabricatorTransactions::TYPE_MFA:
        return 'fa-vcard';
    }

    return 'fa-pencil';
  }

  public function getToken() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_TOKEN:
        $old = $this->getOldValue();
        $new = $this->getNewValue();
        if ($new) {
          $icon = substr($new, 10);
        } else {
          $icon = substr($old, 10);
        }
        return array($icon, !$this->getNewValue());
    }

    return array(null, null);
  }

  public function getColor() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT;
        $comment = $this->getComment();
        if ($comment && $comment->getIsRemoved()) {
          return 'black';
        }
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($this->getMetadataValue('edge:type')) {
          case DiffusionCommitRevertedByCommitEdgeType::EDGECONST:
            return 'pink';
          case DiffusionCommitRevertsCommitEdgeType::EDGECONST:
            return 'sky';
        }
        break;
      case PhabricatorTransactions::TYPE_MFA;
        return 'pink';
    }
    return null;
  }

  protected function getTransactionCustomField() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $key = $this->getMetadataValue('customfield:key');
        if (!$key) {
          return null;
        }

        $object = $this->getObject();

        if (!($object instanceof PhabricatorCustomFieldInterface)) {
          return null;
        }

        $field = PhabricatorCustomField::getObjectField(
          $object,
          PhabricatorCustomField::ROLE_APPLICATIONTRANSACTIONS,
          $key);
        if (!$field) {
          return null;
        }

        $field->setViewer($this->getViewer());
        return $field;
    }

    return null;
  }

  public function shouldHide() {
    // Never hide comments.
    if ($this->hasComment()) {
      return false;
    }

    $xaction_type = $this->getTransactionType();

    // Always hide requests for object history.
    if ($xaction_type === PhabricatorTransactions::TYPE_HISTORY) {
      return true;
    }

    // Always hide file attach/detach transactions.
    if ($xaction_type === PhabricatorTransactions::TYPE_FILE) {
      if ($this->getMetadataValue('attach.implicit')) {
        return true;
      }
    }

    // Hide creation transactions if the old value is empty. These are
    // transactions like "alice set the task title to: ...", which are
    // essentially never interesting.
    if ($this->getIsCreateTransaction()) {
      switch ($xaction_type) {
        case PhabricatorTransactions::TYPE_CREATE:
        case PhabricatorTransactions::TYPE_VIEW_POLICY:
        case PhabricatorTransactions::TYPE_EDIT_POLICY:
        case PhabricatorTransactions::TYPE_JOIN_POLICY:
        case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        case PhabricatorTransactions::TYPE_SPACE:
          break;
        case PhabricatorTransactions::TYPE_SUBTYPE:
          return true;
        default:
          $old = $this->getOldValue();

          if (is_array($old) && !$old) {
            return true;
          }

          if (!is_array($old)) {
            if ($old === '' || $old === null) {
              return true;
            }

            // The integer 0 is also uninteresting by default; this is often
            // an "off" flag for something like "All Day Event".
            if ($old === 0) {
              return true;
            }
          }

          break;
      }
    }

    // Hide creation transactions setting values to defaults, even if
    // the old value is not empty. For example, tasks may have a global
    // default view policy of "All Users", but a particular form sets the
    // policy to "Administrators". The transaction corresponding to this
    // change is not interesting, since it is the default behavior of the
    // form.

    if ($this->getIsCreateTransaction()) {
      if ($this->getIsDefaultTransaction()) {
        return true;
      }
    }

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
      case PhabricatorTransactions::TYPE_SPACE:
        if ($this->getIsCreateTransaction()) {
          break;
        }

        // TODO: Remove this eventually, this is handling old changes during
        // object creation prior to the introduction of "create" and "default"
        // transaction display flags.

        // NOTE: We can also hit this case with Space transactions that later
        // update a default space (`null`) to an explicit space, so handling
        // the Space case may require some finesse.

        if ($this->getOldValue() === null) {
          return true;
        } else {
          return false;
        }
        break;
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          return $field->shouldHideInApplicationTransactions($this);
        }
        break;
      case PhabricatorTransactions::TYPE_COLUMNS:
        return !$this->getInterestingMoves($this->getNewValue());
      case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $this->getMetadataValue('edge:type');
        switch ($edge_type) {
          case PhabricatorObjectMentionsObjectEdgeType::EDGECONST:
          case ManiphestTaskHasDuplicateTaskEdgeType::EDGECONST:
          case ManiphestTaskIsDuplicateOfTaskEdgeType::EDGECONST:
          case PhabricatorMutedEdgeType::EDGECONST:
          case PhabricatorMutedByEdgeType::EDGECONST:
            return true;
          case PhabricatorObjectMentionedByObjectEdgeType::EDGECONST:
            $record = PhabricatorEdgeChangeRecord::newFromTransaction($this);
            $add = $record->getAddedPHIDs();
            $add_value = reset($add);
            $add_handle = $this->getHandle($add_value);
            if ($add_handle->getPolicyFiltered()) {
              return true;
            }
            return false;
            break;
          default:
            break;
        }
        break;

      case PhabricatorTransactions::TYPE_INLINESTATE:
        list($done, $undone) = $this->getInterestingInlineStateChangeCounts();

        if (!$done && !$undone) {
          return true;
        }

        break;

    }

    return false;
  }

  public function shouldHideForMail(array $xactions) {
    if ($this->isSelfSubscription()) {
      return true;
    }

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_TOKEN:
        return true;
      case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $this->getMetadataValue('edge:type');
        switch ($edge_type) {
          case PhabricatorObjectMentionsObjectEdgeType::EDGECONST:
          case PhabricatorObjectMentionedByObjectEdgeType::EDGECONST:
          case DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST:
          case DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST:
          case ManiphestTaskHasCommitEdgeType::EDGECONST:
          case DiffusionCommitHasTaskEdgeType::EDGECONST:
          case DiffusionCommitHasRevisionEdgeType::EDGECONST:
          case DifferentialRevisionHasCommitEdgeType::EDGECONST:
            return true;
          case PhabricatorProjectObjectHasProjectEdgeType::EDGECONST:
            // When an object is first created, we hide any corresponding
            // project transactions in the web UI because you can just look at
            // the UI element elsewhere on screen to see which projects it
            // is tagged with. However, in mail there's no other way to get
            // this information, and it has some amount of value to users, so
            // we keep the transaction. See T10493.
            return false;
          default:
            break;
        }
        break;
    }

    if ($this->isInlineCommentTransaction()) {
      $inlines = array();

      // If there's a normal comment, we don't need to publish the inline
      // transaction, since the normal comment covers things.
      foreach ($xactions as $xaction) {
        if ($xaction->isInlineCommentTransaction()) {
          $inlines[] = $xaction;
          continue;
        }

        // We found a normal comment, so hide this inline transaction.
        if ($xaction->hasComment()) {
          return true;
        }
      }

      // If there are several inline comments, only publish the first one.
      if ($this !== head($inlines)) {
        return true;
      }
    }

    return $this->shouldHide();
  }

  public function shouldHideForFeed() {
    if ($this->isSelfSubscription()) {
      return true;
    }

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_TOKEN:
      case PhabricatorTransactions::TYPE_MFA:
        return true;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        // See T8952. When an application (usually Herald) modifies
        // subscribers, this tends to be very uninteresting.
        if ($this->isApplicationAuthor()) {
          return true;
        }
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $this->getMetadataValue('edge:type');
        switch ($edge_type) {
          case PhabricatorObjectMentionsObjectEdgeType::EDGECONST:
          case PhabricatorObjectMentionedByObjectEdgeType::EDGECONST:
          case DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST:
          case DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST:
          case ManiphestTaskHasCommitEdgeType::EDGECONST:
          case DiffusionCommitHasTaskEdgeType::EDGECONST:
          case DiffusionCommitHasRevisionEdgeType::EDGECONST:
          case DifferentialRevisionHasCommitEdgeType::EDGECONST:
            return true;
          default:
            break;
        }
        break;
     case PhabricatorTransactions::TYPE_INLINESTATE:
       return true;
    }

    return $this->shouldHide();
  }

  public function shouldHideForNotifications() {
    return $this->shouldHideForFeed();
  }

  private function getTitleForMailWithRenderingTarget($new_target) {
    $old_target = $this->getRenderingTarget();
    try {
      $this->setRenderingTarget($new_target);
      $result = $this->getTitleForMail();
    } catch (Exception $ex) {
      $this->setRenderingTarget($old_target);
      throw $ex;
    }
    $this->setRenderingTarget($old_target);
    return $result;
  }

  public function getTitleForMail() {
    return $this->getTitle();
  }

  public function getTitleForTextMail() {
    return $this->getTitleForMailWithRenderingTarget(self::TARGET_TEXT);
  }

  public function getTitleForHTMLMail() {
    // TODO: For now, rendering this with TARGET_HTML generates links with
    // bad targets ("/x/y/" instead of "https://dev.example.com/x/y/"). Throw
    // a rug over the issue for the moment. See T12921.

    $title = $this->getTitleForMailWithRenderingTarget(self::TARGET_TEXT);
    if ($title === null) {
      return null;
    }

    if ($this->hasChangeDetails()) {
      $details_uri = $this->getChangeDetailsURI();
      $details_uri = PhabricatorEnv::getProductionURI($details_uri);

      $show_details = phutil_tag(
        'a',
        array(
          'href' => $details_uri,
        ),
        pht('(Show Details)'));

      $title = array($title, ' ', $show_details);
    }

    return $title;
  }

  public function getChangeDetailsURI() {
    return '/transactions/detail/'.$this->getPHID().'/';
  }

  public function getBodyForMail() {
    if ($this->isInlineCommentTransaction()) {
      // We don't return inline comment content as mail body content, because
      // applications need to contextualize it (by adding line numbers, for
      // example) in order for it to make sense.
      return null;
    }

    $comment = $this->getComment();
    if ($comment && strlen($comment->getContent())) {
      return $comment->getContent();
    }

    return null;
  }

  public function getNoEffectDescription() {

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return pht('You can not post an empty comment.');
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return pht(
          'This %s already has that view policy.',
          $this->getApplicationObjectTypeName());
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return pht(
          'This %s already has that edit policy.',
          $this->getApplicationObjectTypeName());
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return pht(
          'This %s already has that join policy.',
          $this->getApplicationObjectTypeName());
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        return pht(
          'This %s already has that interact policy.',
          $this->getApplicationObjectTypeName());
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return pht(
          'All users are already subscribed to this %s.',
          $this->getApplicationObjectTypeName());
      case PhabricatorTransactions::TYPE_SPACE:
        return pht('This object is already in that space.');
      case PhabricatorTransactions::TYPE_EDGE:
        return pht('Edges already exist; transaction has no effect.');
      case PhabricatorTransactions::TYPE_COLUMNS:
        return pht(
          'You have not moved this object to any columns it is not '.
          'already in.');
      case PhabricatorTransactions::TYPE_MFA:
        return pht(
          'You can not sign a transaction group that has no other '.
          'effects.');
    }

    return pht(
      'Transaction (of type "%s") has no effect.',
      $this->getTransactionType());
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s created this object.',
          $this->renderHandleLink($author_phid));
      case PhabricatorTransactions::TYPE_COMMENT:
        return pht(
          '%s added a comment.',
          $this->renderHandleLink($author_phid));
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        if ($this->getIsCreateTransaction()) {
          return pht(
            '%s created this object with visibility "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderPolicyName($new, 'new'));
        } else {
          return pht(
            '%s changed the visibility from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderPolicyName($old, 'old'),
            $this->renderPolicyName($new, 'new'));
        }
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        if ($this->getIsCreateTransaction()) {
          return pht(
            '%s created this object with edit policy "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderPolicyName($new, 'new'));
        } else {
          return pht(
            '%s changed the edit policy from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderPolicyName($old, 'old'),
            $this->renderPolicyName($new, 'new'));
        }
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        if ($this->getIsCreateTransaction()) {
          return pht(
            '%s created this object with join policy "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderPolicyName($new, 'new'));
        } else {
          return pht(
            '%s changed the join policy from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderPolicyName($old, 'old'),
            $this->renderPolicyName($new, 'new'));
        }
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        if ($this->getIsCreateTransaction()) {
          return pht(
            '%s created this object with interact policy "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderPolicyName($new, 'new'));
        } else {
          return pht(
            '%s changed the interact policy from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderPolicyName($old, 'old'),
            $this->renderPolicyName($new, 'new'));
        }
      case PhabricatorTransactions::TYPE_SPACE:
        if ($this->getIsCreateTransaction()) {
          return pht(
            '%s created this object in space %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s shifted this object from the %s space to the %s space.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s edited subscriber(s), added %d: %s; removed %d: %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderSubscriberList($add, 'add'),
            count($rem),
            $this->renderSubscriberList($rem, 'rem'));
        } else if ($add) {
          return pht(
            '%s added %d subscriber(s): %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderSubscriberList($add, 'add'));
        } else if ($rem) {
          return pht(
            '%s removed %d subscriber(s): %s.',
            $this->renderHandleLink($author_phid),
            count($rem),
            $this->renderSubscriberList($rem, 'rem'));
        } else {
          // This is used when rendering previews, before the user actually
          // selects any CCs.
          return pht(
            '%s updated subscribers...',
            $this->renderHandleLink($author_phid));
        }
        break;
      case PhabricatorTransactions::TYPE_FILE:
        $add = array_diff_key($new, $old);
        $add = array_keys($add);

        $rem = array_diff_key($old, $new);
        $rem = array_keys($rem);

        $mod = array();
        foreach ($old + $new as $key => $ignored) {
          if (!isset($old[$key])) {
            continue;
          }

          if (!isset($new[$key])) {
            continue;
          }

          if ($old[$key] === $new[$key]) {
            continue;
          }

          $mod[] = $key;
        }

        // Specialize the specific case of only modifying files and upgrading
        // references to attachments. This is accessible via the UI and can
        // be shown more clearly than the generic default transaction shows
        // it.

        $mode_reference = PhabricatorFileAttachment::MODE_REFERENCE;
        $mode_attach = PhabricatorFileAttachment::MODE_ATTACH;

        $is_refattach = false;
        if ($mod && !$add && !$rem) {
          $all_refattach = true;
          foreach ($mod as $phid) {
            if (idx($old, $phid) !== $mode_reference) {
              $all_refattach = false;
              break;
            }
            if (idx($new, $phid) !== $mode_attach) {
              $all_refattach = false;
              break;
            }
          }
          $is_refattach = $all_refattach;
        }

        if ($is_refattach) {
          return pht(
            '%s attached %s referenced file(s): %s.',
            $this->renderHandleLink($author_phid),
            phutil_count($mod),
            $this->renderHandleList($mod));
        } else if ($add && $rem && $mod) {
          return pht(
            '%s updated %s attached file(s), added %s: %s; removed %s: %s; '.
            'modified %s: %s.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($add) + count($rem)),
            phutil_count($add),
            $this->renderHandleList($add),
            phutil_count($rem),
            $this->renderHandleList($rem),
            phutil_count($mod),
            $this->renderHandleList($mod));
        } else if ($add && $rem) {
          return pht(
            '%s updated %s attached file(s), added %s: %s; removed %s: %s.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($add) + count($rem)),
            phutil_count($add),
            $this->renderHandleList($add),
            phutil_count($rem),
            $this->renderHandleList($rem));
        } else if ($add && $mod) {
          return pht(
            '%s updated %s attached file(s), added %s: %s; modified %s: %s.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($add) + count($mod)),
            phutil_count($add),
            $this->renderHandleList($add),
            phutil_count($mod),
            $this->renderHandleList($mod));
        } else if ($rem && $mod) {
          return pht(
            '%s updated %s attached file(s), removed %s: %s; modified %s: %s.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($rem) + count($mod)),
            phutil_count($rem),
            $this->renderHandleList($rem),
            phutil_count($mod),
            $this->renderHandleList($mod));
        } else if ($add) {
          return pht(
            '%s attached %s file(s): %s.',
            $this->renderHandleLink($author_phid),
            phutil_count($add),
            $this->renderHandleList($add));
        } else if ($rem) {
          return pht(
            '%s removed %s attached file(s): %s.',
            $this->renderHandleLink($author_phid),
            phutil_count($rem),
            $this->renderHandleList($rem));
        } else if ($mod) {
          return pht(
            '%s modified %s attached file(s): %s.',
            $this->renderHandleLink($author_phid),
            phutil_count($mod),
            $this->renderHandleList($mod));
        } else {
          return pht(
            '%s attached files...',
            $this->renderHandleLink($author_phid));
        }

        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $record = PhabricatorEdgeChangeRecord::newFromTransaction($this);
        $add = $record->getAddedPHIDs();
        $rem = $record->getRemovedPHIDs();

        $type = $this->getMetadata('edge:type');
        $type = head($type);

        try {
          $type_obj = PhabricatorEdgeType::getByConstant($type);
        } catch (Exception $ex) {
          // Recover somewhat gracefully from edge transactions which
          // we don't have the classes for.
          return pht(
            '%s edited an edge.',
            $this->renderHandleLink($author_phid));
        }

        if ($add && $rem) {
          return $type_obj->getTransactionEditString(
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($add) + count($rem)),
            phutil_count($add),
            $this->renderHandleList($add),
            phutil_count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          return $type_obj->getTransactionAddString(
            $this->renderHandleLink($author_phid),
            phutil_count($add),
            $this->renderHandleList($add));
        } else if ($rem) {
          return $type_obj->getTransactionRemoveString(
            $this->renderHandleLink($author_phid),
            phutil_count($rem),
            $this->renderHandleList($rem));
        } else {
          return $type_obj->getTransactionPreviewString(
            $this->renderHandleLink($author_phid));
        }

      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          return $field->getApplicationTransactionTitle($this);
        } else {
          $developer_mode = 'phabricator.developer-mode';
          $is_developer = PhabricatorEnv::getEnvConfig($developer_mode);
          if ($is_developer) {
            return pht(
              '%s edited a custom field (with key "%s").',
              $this->renderHandleLink($author_phid),
              $this->getMetadata('customfield:key'));
          } else {
            return pht(
              '%s edited a custom field.',
              $this->renderHandleLink($author_phid));
          }
        }

      case PhabricatorTransactions::TYPE_TOKEN:
        if ($old && $new) {
          return pht(
            '%s updated a token.',
            $this->renderHandleLink($author_phid));
        } else if ($old) {
          return pht(
            '%s rescinded a token.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s awarded a token.',
            $this->renderHandleLink($author_phid));
        }

      case PhabricatorTransactions::TYPE_INLINESTATE:
        list($done, $undone) = $this->getInterestingInlineStateChangeCounts();
        if ($done && $undone) {
          return pht(
            '%s marked %s inline comment(s) as done and %s inline comment(s) '.
            'as not done.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber($done),
            new PhutilNumber($undone));
        } else if ($done) {
          return pht(
            '%s marked %s inline comment(s) as done.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber($done));
        } else {
          return pht(
            '%s marked %s inline comment(s) as not done.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber($undone));
        }
        break;

      case PhabricatorTransactions::TYPE_COLUMNS:
        $moves = $this->getInterestingMoves($new);
        if (count($moves) == 1) {
          $move = head($moves);
          $from_columns = $move['fromColumnPHIDs'];
          $to_column = $move['columnPHID'];
          $board_phid = $move['boardPHID'];
          if (count($from_columns) == 1) {
            return pht(
              '%s moved this task from %s to %s on the %s board.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink(head($from_columns)),
              $this->renderHandleLink($to_column),
              $this->renderHandleLink($board_phid));
          } else {
            return pht(
              '%s moved this task to %s on the %s board.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($to_column),
              $this->renderHandleLink($board_phid));
          }
        } else {
          $fragments = array();
          foreach ($moves as $move) {
            $to_column = $move['columnPHID'];
            $board_phid = $move['boardPHID'];
            $fragments[] = pht(
              '%s (%s)',
              $this->renderHandleLink($board_phid),
              $this->renderHandleLink($to_column));
          }

          return pht(
            '%s moved this task on %s board(s): %s.',
            $this->renderHandleLink($author_phid),
            phutil_count($moves),
            phutil_implode_html(', ', $fragments));
        }
        break;


      case PhabricatorTransactions::TYPE_MFA:
        return pht(
          '%s signed these changes with MFA.',
          $this->renderHandleLink($author_phid));

      default:
        // In developer mode, provide a better hint here about which string
        // we're missing.
        $developer_mode = 'phabricator.developer-mode';
        $is_developer = PhabricatorEnv::getEnvConfig($developer_mode);
        if ($is_developer) {
          return pht(
            '%s edited this object (transaction type "%s").',
            $this->renderHandleLink($author_phid),
            $this->getTransactionType());
        } else {
          return pht(
            '%s edited this %s.',
            $this->renderHandleLink($author_phid),
            $this->getApplicationObjectTypeName());
        }
    }
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s created %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_COMMENT:
        return pht(
          '%s added a comment to %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return pht(
          '%s changed the visibility for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return pht(
          '%s changed the edit policy for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return pht(
          '%s changed the join policy for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        return pht(
          '%s changed the interact policy for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return pht(
          '%s updated subscribers of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_SPACE:
        if ($this->getIsCreateTransaction()) {
          return pht(
            '%s created %s in the %s space.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s shifted %s from the %s space to the %s space.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
      case PhabricatorTransactions::TYPE_EDGE:
        $record = PhabricatorEdgeChangeRecord::newFromTransaction($this);
        $add = $record->getAddedPHIDs();
        $rem = $record->getRemovedPHIDs();

        $type = $this->getMetadata('edge:type');
        $type = head($type);

        $type_obj = PhabricatorEdgeType::getByConstant($type);

        if ($add && $rem) {
          return $type_obj->getFeedEditString(
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            new PhutilNumber(count($add) + count($rem)),
            phutil_count($add),
            $this->renderHandleList($add),
            phutil_count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          return $type_obj->getFeedAddString(
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            phutil_count($add),
            $this->renderHandleList($add));
        } else if ($rem) {
          return $type_obj->getFeedRemoveString(
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            phutil_count($rem),
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s edited edge metadata for %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }

      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          return $field->getApplicationTransactionTitleForFeed($this);
        } else {
          return pht(
            '%s edited a custom field on %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }

      case PhabricatorTransactions::TYPE_COLUMNS:
        $moves = $this->getInterestingMoves($new);
        if (count($moves) == 1) {
          $move = head($moves);
          $from_columns = $move['fromColumnPHIDs'];
          $to_column = $move['columnPHID'];
          $board_phid = $move['boardPHID'];
          if (count($from_columns) == 1) {
            return pht(
              '%s moved %s from %s to %s on the %s board.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid),
              $this->renderHandleLink(head($from_columns)),
              $this->renderHandleLink($to_column),
              $this->renderHandleLink($board_phid));
          } else {
            return pht(
              '%s moved %s to %s on the %s board.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid),
              $this->renderHandleLink($to_column),
              $this->renderHandleLink($board_phid));
          }
        } else {
          $fragments = array();
          foreach ($moves as $move) {
            $fragments[] = pht(
              '%s (%s)',
              $this->renderHandleLink($board_phid),
              $this->renderHandleLink($to_column));
          }

          return pht(
            '%s moved %s on %s board(s): %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            phutil_count($moves),
            phutil_implode_html(', ', $fragments));
        }
        break;

      case PhabricatorTransactions::TYPE_MFA:
        return null;

    }

    return $this->getTitle();
  }

  public function getMarkupFieldsForFeed(PhabricatorFeedStory $story) {
    $fields = array();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $text = $this->getComment()->getContent();
        if (strlen($text)) {
          $fields[] = 'comment/'.$this->getID();
        }
        break;
    }

    return $fields;
  }

  public function getMarkupTextForFeed(PhabricatorFeedStory $story, $field) {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $text = $this->getComment()->getContent();
        return PhabricatorMarkupEngine::summarize($text);
    }

    return null;
  }

  public function getBodyForFeed(PhabricatorFeedStory $story) {
    $remarkup = $this->getRemarkupBodyForFeed($story);
    if ($remarkup !== null) {
      $remarkup = PhabricatorMarkupEngine::summarize($remarkup);
      return new PHUIRemarkupView($this->viewer, $remarkup);
    }

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $body = null;

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $text = $this->getComment()->getContent();
        if (strlen($text)) {
          $body = $story->getMarkupFieldOutput('comment/'.$this->getID());
        }
        break;
    }

    return $body;
  }

  public function getRemarkupBodyForFeed(PhabricatorFeedStory $story) {
    return null;
  }

  public function getActionStrength() {
    if ($this->isInlineCommentTransaction()) {
      return 25;
    }

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return 50;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        if ($this->isSelfSubscription()) {
          // Make this weaker than TYPE_COMMENT.
          return 25;
        }

        // In other cases, subscriptions are more interesting than comments
        // (which are shown anyway) but less interesting than any other type of
        // transaction.
        return 75;
      case PhabricatorTransactions::TYPE_MFA:
        // We want MFA signatures to render at the top of transaction groups,
        // on top of the things they signed.
        return 1000;
    }

    return 100;
  }

  public function isCommentTransaction() {
    if ($this->hasComment()) {
      return true;
    }

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return true;
    }

    return false;
  }

  public function isInlineCommentTransaction() {
    return false;
  }

  public function getActionName() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return pht('Commented On');
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_INTERACT_POLICY:
        return pht('Changed Policy');
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return pht('Changed Subscribers');
      default:
        return pht('Updated');
    }
  }

  public function getMailTags() {
    return array();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_FILE:
        return true;
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          return $field->getApplicationTransactionHasChangeDetails($this);
        }
        break;
    }
    return false;
  }

  public function hasChangeDetailsForMail() {
    return $this->hasChangeDetails();
  }

  public function renderChangeDetailsForMail(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_FILE:
        return false;
    }

    $view = $this->renderChangeDetails($viewer);
    if ($view instanceof PhabricatorApplicationTransactionTextDiffDetailView) {
      return $view->renderForMail();
    }
    return null;
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_FILE:
        return $this->newFileTransactionChangeDetails($viewer);
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          return $field->getApplicationTransactionChangeDetails($this, $viewer);
        }
        break;
    }

    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $this->getOldValue(),
      $this->getNewValue());
  }

  public function renderTextCorpusChangeDetails(
    PhabricatorUser $viewer,
    $old,
    $new) {
    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setUser($viewer)
      ->setOldText($old)
      ->setNewText($new);
  }

  public function attachTransactionGroup(array $group) {
    assert_instances_of($group, __CLASS__);
    $this->transactionGroup = $group;
    return $this;
  }

  public function getTransactionGroup() {
    return $this->transactionGroup;
  }

  /**
   * Should this transaction be visually grouped with an existing transaction
   * group?
   *
   * @param list<PhabricatorApplicationTransaction> List of transactions.
   * @return bool True to display in a group with the other transactions.
   */
  public function shouldDisplayGroupWith(array $group) {
    $this_source = null;
    if ($this->getContentSource()) {
      $this_source = $this->getContentSource()->getSource();
    }

    $type_mfa = PhabricatorTransactions::TYPE_MFA;

    foreach ($group as $xaction) {
      // Don't group transactions by different authors.
      if ($xaction->getAuthorPHID() != $this->getAuthorPHID()) {
        return false;
      }

      // Don't group transactions for different objects.
      if ($xaction->getObjectPHID() != $this->getObjectPHID()) {
        return false;
      }

      // Don't group anything into a group which already has a comment.
      if ($xaction->isCommentTransaction()) {
        return false;
      }

      // Don't group transactions from different content sources.
      $other_source = null;
      if ($xaction->getContentSource()) {
        $other_source = $xaction->getContentSource()->getSource();
      }

      if ($other_source != $this_source) {
        return false;
      }

      // Don't group transactions which happened more than 2 minutes apart.
      $apart = abs($xaction->getDateCreated() - $this->getDateCreated());
      if ($apart > (60 * 2)) {
        return false;
      }

      // Don't group silent and nonsilent transactions together.
      $is_silent = $this->getIsSilentTransaction();
      if ($is_silent != $xaction->getIsSilentTransaction()) {
        return false;
      }

      // Don't group MFA and non-MFA transactions together.
      $is_mfa = $this->getIsMFATransaction();
      if ($is_mfa != $xaction->getIsMFATransaction()) {
        return false;
      }

      // Don't group two "Sign with MFA" transactions together.
      if ($this->getTransactionType() === $type_mfa) {
        if ($xaction->getTransactionType() === $type_mfa) {
          return false;
        }
      }

      // Don't group lock override and non-override transactions together.
      $is_override = $this->getIsLockOverrideTransaction();
      if ($is_override != $xaction->getIsLockOverrideTransaction()) {
        return false;
      }
    }

    return true;
  }

  public function renderExtraInformationLink() {
    $herald_xscript_id = $this->getMetadataValue('herald:transcriptID');

    if ($herald_xscript_id) {
      return phutil_tag(
        'a',
        array(
          'href' => '/herald/transcript/'.$herald_xscript_id.'/',
        ),
        pht('View Herald Transcript'));
    }

    return null;
  }

  public function renderAsTextForDoorkeeper(
    DoorkeeperFeedStoryPublisher $publisher,
    PhabricatorFeedStory $story,
    array $xactions) {

    $text = array();
    $body = array();

    foreach ($xactions as $xaction) {
      $xaction_body = $xaction->getBodyForMail();
      if ($xaction_body !== null) {
        $body[] = $xaction_body;
      }

      if ($xaction->shouldHideForMail($xactions)) {
        continue;
      }

      $old_target = $xaction->getRenderingTarget();
      $new_target = self::TARGET_TEXT;
      $xaction->setRenderingTarget($new_target);

      if ($publisher->getRenderWithImpliedContext()) {
        $text[] = $xaction->getTitle();
      } else {
        $text[] = $xaction->getTitleForFeed();
      }

      $xaction->setRenderingTarget($old_target);
    }

    $text = implode("\n", $text);
    $body = implode("\n\n", $body);

    return rtrim($text."\n\n".$body);
  }

  /**
   * Test if this transaction is just a user subscribing or unsubscribing
   * themselves.
   */
  private function isSelfSubscription() {
    $type = $this->getTransactionType();
    if ($type != PhabricatorTransactions::TYPE_SUBSCRIBERS) {
      return false;
    }

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($old, $new);
    $rem = array_diff($new, $old);

    if ((count($add) + count($rem)) != 1) {
      // More than one user affected.
      return false;
    }

    $affected_phid = head(array_merge($add, $rem));
    if ($affected_phid != $this->getAuthorPHID()) {
      // Affected user is someone else.
      return false;
    }

    return true;
  }

  private function isApplicationAuthor() {
    $author_phid = $this->getAuthorPHID();
    $author_type = phid_get_type($author_phid);
    $application_type = PhabricatorApplicationApplicationPHIDType::TYPECONST;
    return ($author_type == $application_type);
  }


  private function getInterestingMoves(array $moves) {
    // Remove moves which only shift the position of a task within a column.
    foreach ($moves as $key => $move) {
      $from_phids = array_fuse($move['fromColumnPHIDs']);
      if (isset($from_phids[$move['columnPHID']])) {
        unset($moves[$key]);
      }
    }

    return $moves;
  }

  private function getInterestingInlineStateChangeCounts() {
    // See PHI995. Newer inline state transactions have additional details
    // which we use to tailor the rendering behavior. These details are not
    // present on older transactions.
    $details = $this->getMetadataValue('inline.details', array());

    $new = $this->getNewValue();

    $done = 0;
    $undone = 0;
    foreach ($new as $phid => $state) {
      $is_done = ($state == PhabricatorInlineComment::STATE_DONE);

      // See PHI995. If you're marking your own inline comments as "Done",
      // don't count them when rendering a timeline story. In the case where
      // you're only affecting your own comments, this will hide the
      // "alice marked X comments as done" story entirely.

      // Usually, this happens when you pre-mark inlines as "done" and submit
      // them yourself. We'll still generate an "alice added inline comments"
      // story (in most cases/contexts), but the state change story is largely
      // just clutter and slightly confusing/misleading.

      $inline_details = idx($details, $phid, array());
      $inline_author_phid = idx($inline_details, 'authorPHID');
      if ($inline_author_phid) {
        if ($inline_author_phid == $this->getAuthorPHID()) {
          if ($is_done) {
            continue;
          }
        }
      }

      if ($is_done) {
        $done++;
      } else {
        $undone++;
      }
    }

    return array($done, $undone);
  }

  public function newGlobalSortVector() {
    return id(new PhutilSortVector())
      ->addInt(-$this->getDateCreated())
      ->addString($this->getPHID());
  }

  public function newActionStrengthSortVector() {
    return id(new PhutilSortVector())
      ->addInt(-$this->getActionStrength());
  }

  private function newFileTransactionChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $phids = array_keys($old + $new);
    $handles = $viewer->loadHandles($phids);

    $names = array(
      PhabricatorFileAttachment::MODE_REFERENCE => pht('Referenced'),
      PhabricatorFileAttachment::MODE_ATTACH => pht('Attached'),
    );

    $rows = array();
    foreach ($old + $new as $phid => $ignored) {
      $handle = $handles[$phid];

      $old_mode = idx($old, $phid);
      $new_mode = idx($new, $phid);

      if ($old_mode === null) {
        $old_name = pht('None');
      } else if (isset($names[$old_mode])) {
        $old_name = $names[$old_mode];
      } else {
        $old_name = pht('Unknown ("%s")', $old_mode);
      }

      if ($new_mode === null) {
        $new_name = pht('Detached');
      } else if (isset($names[$new_mode])) {
        $new_name = $names[$new_mode];
      } else {
        $new_name = pht('Unknown ("%s")', $new_mode);
      }

      $rows[] = array(
        $handle->renderLink(),
        $old_name,
        $new_name,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('File'),
          pht('Old Mode'),
          pht('New Mode'),
        ))
      ->setColumnClasses(
        array(
          'pri',
        ));

    return id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_SMALL)
      ->appendChild($table);
  }



/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Transactions are visible to users that can see the object which was '.
      'acted upon. Some transactions - in particular, comments - are '.
      'editable by the transaction author.');
  }

  public function getModularType() {
    return null;
  }

  public function setForceNotifyPHIDs(array $phids) {
    $this->setMetadataValue('notify.force', $phids);
    return $this;
  }

  public function getForceNotifyPHIDs() {
    return $this->getMetadataValue('notify.force', array());
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $comment_template = $this->getApplicationTransactionCommentObject();

      if ($comment_template) {
        $comments = $comment_template->loadAllWhere(
          'transactionPHID = %s',
          $this->getPHID());
        foreach ($comments as $comment) {
          $engine->destroyObject($comment);
        }
      }

      $this->delete();
    $this->saveTransaction();
  }

}
