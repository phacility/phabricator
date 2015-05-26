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
      case PhabricatorTransactions::TYPE_BUILDABLE:
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
    throw new PhutilMethodNotImplementedException();
  }

  public function getApplicationTransactionViewObject() {
    return new PhabricatorApplicationTransactionView();
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
    return $this->getComment() && strlen($this->getComment()->getContent());
  }

  public function getComment() {
    if ($this->commentNotLoaded) {
      throw new Exception(pht('Comment for this transaction was not loaded.'));
    }
    return $this->comment;
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

    if ($this->getComment()) {
      $blocks[] = $this->getComment()->getContent();
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
      case PhabricatorTransactions::TYPE_EDGE:
        $phids[] = ipull($old, 'dst');
        $phids[] = ipull($new, 'dst');
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        if (!PhabricatorPolicyQuery::isGlobalPolicy($old)) {
          $phids[] = array($old);
        }
        if (!PhabricatorPolicyQuery::isGlobalPolicy($new)) {
          $phids[] = array($new);
        }
        break;
      case PhabricatorTransactions::TYPE_TOKEN:
        break;
      case PhabricatorTransactions::TYPE_BUILDABLE:
        $phid = $this->getMetadataValue('harbormaster:buildablePHID');
        if ($phid) {
          $phids[] = array($phid);
        }
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
      return $this->getHandle($phid)->renderLink();
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
    if ($this->renderingTarget == self::TARGET_HTML) {
      switch ($policy->getType()) {
        case PhabricatorPolicyType::TYPE_CUSTOM:
          $policy->setHref('/transactions/'.$state.'/'.$this->getPHID().'/');
          $policy->setWorkflow(true);
          break;
        default:
          break;
      }
      $output = $policy->renderDescription();
    } else {
      $output = hsprintf('%s', $policy->getFullName());
    }
    return $output;
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $comment = $this->getComment();
        if ($comment && $comment->getIsRemoved()) {
          return 'fa-eraser';
        }
        return 'fa-comment';
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return 'fa-envelope';
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return 'fa-lock';
      case PhabricatorTransactions::TYPE_EDGE:
        return 'fa-link';
      case PhabricatorTransactions::TYPE_BUILDABLE:
        return 'fa-wrench';
      case PhabricatorTransactions::TYPE_TOKEN:
        return 'fa-trophy';
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
      case PhabricatorTransactions::TYPE_BUILDABLE:
        switch ($this->getNewValue()) {
          case HarbormasterBuildable::STATUS_PASSED:
            return 'green';
          case HarbormasterBuildable::STATUS_FAILED:
            return 'red';
        }
        break;
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

        $field = PhabricatorCustomField::getObjectField(
          $this->getObject(),
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
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
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
      case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $this->getMetadataValue('edge:type');
        switch ($edge_type) {
          case PhabricatorObjectMentionsObjectEdgeType::EDGECONST:
            return true;
            break;
          case PhabricatorObjectMentionedByObjectEdgeType::EDGECONST:
            $new = ipull($this->getNewValue(), 'dst');
            $old = ipull($this->getOldValue(), 'dst');
            $add = array_diff($new, $old);
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
    }

    return false;
  }

  public function shouldHideForMail(array $xactions) {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_TOKEN:
        return true;
      case PhabricatorTransactions::TYPE_BUILDABLE:
        switch ($this->getNewValue()) {
          case HarbormasterBuildable::STATUS_FAILED:
            // For now, only ever send mail when builds fail. We might let
            // you customize this later, but in most cases this is probably
            // completely uninteresting.
            return false;
        }
        return true;
     case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $this->getMetadataValue('edge:type');
        switch ($edge_type) {
          case PhabricatorObjectMentionsObjectEdgeType::EDGECONST:
          case PhabricatorObjectMentionedByObjectEdgeType::EDGECONST:
            return true;
            break;
          default:
            break;
        }
        break;
    }

    // If a transaction publishes an inline comment:
    //
    //   - Don't show it if there are other kinds of transactions. The
    //     rationale here is that application mail will make the presence
    //     of inline comments obvious enough by including them prominently
    //     in the body. We could change this in the future if the obviousness
    //     needs to be increased.
    //   - If there are only inline transactions, only show the first
    //     transaction. The rationale is that seeing multiple "added an inline
    //     comment" transactions is not useful.

    if ($this->isInlineCommentTransaction()) {
      foreach ($xactions as $xaction) {
        if (!$xaction->isInlineCommentTransaction()) {
          return true;
        }
      }
      return ($this !== head($xactions));
    }

    return $this->shouldHide();
  }

  public function shouldHideForFeed() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_TOKEN:
        return true;
      case PhabricatorTransactions::TYPE_BUILDABLE:
        switch ($this->getNewValue()) {
          case HarbormasterBuildable::STATUS_FAILED:
            // For now, don't notify on build passes either. These are pretty
            // high volume and annoying, with very little present value. We
            // might want to turn them back on in the specific case of
            // build successes on the current document?
            return false;
        }
        return true;
     case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $this->getMetadataValue('edge:type');
        switch ($edge_type) {
          case PhabricatorObjectMentionsObjectEdgeType::EDGECONST:
          case PhabricatorObjectMentionedByObjectEdgeType::EDGECONST:
            return true;
            break;
          default:
            break;
        }
        break;
     case PhabricatorTransactions::TYPE_INLINESTATE:
       return true;
    }

    return $this->shouldHide();
  }

  public function getTitleForMail() {
    return id(clone $this)->setRenderingTarget('text')->getTitle();
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
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return pht(
          'All users are already subscribed to this %s.',
          $this->getApplicationObjectTypeName());
      case PhabricatorTransactions::TYPE_EDGE:
        return pht('Edges already exist; transaction has no effect.');
    }

    return pht('Transaction has no effect.');
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return pht(
          '%s added a comment.',
          $this->renderHandleLink($author_phid));
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return pht(
          '%s changed the visibility of this %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName(),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return pht(
          '%s changed the edit policy of this %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName(),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return pht(
          '%s changed the join policy of this %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName(),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
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
      case PhabricatorTransactions::TYPE_EDGE:
        $new = ipull($new, 'dst');
        $old = ipull($old, 'dst');
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        $type = $this->getMetadata('edge:type');
        $type = head($type);

        $type_obj = PhabricatorEdgeType::getByConstant($type);

        if ($add && $rem) {
          return $type_obj->getTransactionEditString(
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($add) + count($rem)),
            new PhutilNumber(count($add)),
            $this->renderHandleList($add),
            new PhutilNumber(count($rem)),
            $this->renderHandleList($rem));
        } else if ($add) {
          return $type_obj->getTransactionAddString(
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($add)),
            $this->renderHandleList($add));
        } else if ($rem) {
          return $type_obj->getTransactionRemoveString(
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($rem)),
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
          return pht(
            '%s edited a custom field.',
            $this->renderHandleLink($author_phid));
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

      case PhabricatorTransactions::TYPE_BUILDABLE:
        switch ($this->getNewValue()) {
          case HarbormasterBuildable::STATUS_BUILDING:
            return pht(
              '%s started building %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink(
                $this->getMetadataValue('harbormaster:buildablePHID')));
          case HarbormasterBuildable::STATUS_PASSED:
            return pht(
              '%s completed building %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink(
                $this->getMetadataValue('harbormaster:buildablePHID')));
          case HarbormasterBuildable::STATUS_FAILED:
            return pht(
              '%s failed to build %s!',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink(
                $this->getMetadataValue('harbormaster:buildablePHID')));
          default:
            return null;
        }

      case PhabricatorTransactions::TYPE_INLINESTATE:
        $done = 0;
        $undone = 0;
        foreach ($new as $phid => $state) {
          if ($state == PhabricatorInlineCommentInterface::STATE_DONE) {
            $done++;
          } else {
            $undone++;
          }
        }
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

      default:
        return pht(
          '%s edited this %s.',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName());
    }
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
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
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return pht(
          '%s updated subscribers of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_EDGE:
        $new = ipull($new, 'dst');
        $old = ipull($old, 'dst');
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        $type = $this->getMetadata('edge:type');
        $type = head($type);

        $type_obj = PhabricatorEdgeType::getByConstant($type);

        if ($add && $rem) {
          return $type_obj->getFeedEditString(
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            new PhutilNumber(count($add) + count($rem)),
            new PhutilNumber(count($add)),
            $this->renderHandleList($add),
            new PhutilNumber(count($rem)),
            $this->renderHandleList($rem));
        } else if ($add) {
          return $type_obj->getFeedAddString(
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            new PhutilNumber(count($add)),
            $this->renderHandleList($add));
        } else if ($rem) {
          return $type_obj->getFeedRemoveString(
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            new PhutilNumber(count($rem)),
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
      case PhabricatorTransactions::TYPE_BUILDABLE:
        switch ($this->getNewValue()) {
          case HarbormasterBuildable::STATUS_BUILDING:
            return pht(
              '%s started building %s for %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink(
                $this->getMetadataValue('harbormaster:buildablePHID')),
              $this->renderHandleLink($object_phid));
          case HarbormasterBuildable::STATUS_PASSED:
            return pht(
              '%s completed building %s for %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink(
                $this->getMetadataValue('harbormaster:buildablePHID')),
              $this->renderHandleLink($object_phid));
          case HarbormasterBuildable::STATUS_FAILED:
            return pht(
              '%s failed to build %s for %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink(
                $this->getMetadataValue('harbormaster:buildablePHID')),
              $this->renderHandleLink($object_phid));
          default:
            return null;
        }

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

  public function getActionStrength() {
    if ($this->isInlineCommentTransaction()) {
      return 0.25;
    }

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return 0.5;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $old = $this->getOldValue();
        $new = $this->getNewValue();

        $add = array_diff($old, $new);
        $rem = array_diff($new, $old);

        // If this action is the actor subscribing or unsubscribing themselves,
        // it is less interesting. In particular, if someone makes a comment and
        // also implicitly subscribes themselves, we should treat the
        // transaction group as "comment", not "subscribe". In this specific
        // case (one affected user, and that affected user it the actor),
        // decrease the action strength.

        if ((count($add) + count($rem)) != 1) {
          // Not exactly one CC change.
          break;
        }

        $affected_phid = head(array_merge($add, $rem));
        if ($affected_phid != $this->getAuthorPHID()) {
          // Affected user is someone else.
          break;
        }

        // Make this weaker than TYPE_COMMENT.
        return 0.25;
    }
    return 1.0;
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
        return pht('Changed Policy');
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return pht('Changed Subscribers');
      case PhabricatorTransactions::TYPE_BUILDABLE:
        switch ($this->getNewValue()) {
          case HarbormasterBuildable::STATUS_PASSED:
            return pht('Build Passed');
          case HarbormasterBuildable::STATUS_FAILED:
            return pht('Build Failed');
          default:
            return pht('Build Status');
        }
      default:
        return pht('Updated');
    }
  }

  public function getMailTags() {
    return array();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          return $field->getApplicationTransactionHasChangeDetails($this);
        }
        break;
    }
    return false;
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
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

    require_celerity_resource('differential-changeset-view-css');

    $view = id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setUser($viewer)
      ->setOldText($old)
      ->setNewText($new);

    return $view->render();
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


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $comment_template = null;
      try {
        $comment_template = $this->getApplicationTransactionCommentObject();
      } catch (Exception $ex) {
        // Continue; no comments for these transactions.
      }

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
