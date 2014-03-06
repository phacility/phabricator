<?php

abstract class PhabricatorApplicationTransaction
  extends PhabricatorLiskDAO
  implements PhabricatorPolicyInterface {

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
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
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
    throw new Exception("Not implemented!");
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
    $type = PhabricatorApplicationTransactionPHIDTypeTransaction::TYPECONST;
    $subtype = $this->getApplicationTransactionType();

    return PhabricatorPHID::generateNewPHID($type, $subtype);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'oldValue' => self::SERIALIZATION_JSON,
        'newValue' => self::SERIALIZATION_JSON,
        'metadata' => self::SERIALIZATION_JSON,
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
      throw new Exception("Comment for this transaction was not loaded.");
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
          'Transaction ("%s") requires a handle ("%s") that it did not '.
          'load.',
          $this->getPHID(),
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
        'Transaction requires handles and it did not load them.'
      );
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

  public function renderPolicyName($phid) {
    $policy = PhabricatorPolicy::newFromPolicyAndHandle(
      $phid,
      $this->getHandleIfExists($phid));
    if ($this->renderingTarget == self::TARGET_HTML) {
      $output = $policy->renderDescription();
    } else {
      $output = hsprintf('%s', $policy->getFullName());
    }
    return $output;
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return 'comment';
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return 'message';
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return 'lock';
      case PhabricatorTransactions::TYPE_EDGE:
        return 'link';
    }

    return 'edit';
  }

  public function getColor() {
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
    }

    return false;
  }

  public function shouldHideForMail(array $xactions) {
    return $this->shouldHide();
  }

  public function shouldHideForFeed() {
    return $this->shouldHide();
  }

  public function getTitleForMail() {
    return id(clone $this)->setRenderingTarget('text')->getTitle();
  }

  public function getBodyForMail() {
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
          $this->renderPolicyName($old),
          $this->renderPolicyName($new));
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return pht(
          '%s changed the edit policy of this %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName(),
          $this->renderPolicyName($old),
          $this->renderPolicyName($new));
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return pht(
          '%s changed the join policy of this %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName(),
          $this->renderPolicyName($old),
          $this->renderPolicyName($new));
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s edited subscriber(s), added %d: %s; removed %d: %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added %d subscriber(s): %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add));
        } else if ($rem) {
          return pht(
            '%s removed %d subscriber(s): %s.',
            $this->renderHandleLink($author_phid),
            count($rem),
            $this->renderHandleList($rem));
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

        if ($add && $rem) {
          $string = PhabricatorEdgeConfig::getEditStringForEdgeType($type);
          return pht(
            $string,
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          $string = PhabricatorEdgeConfig::getAddStringForEdgeType($type);
          return pht(
            $string,
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add));
        } else if ($rem) {
          $string = PhabricatorEdgeConfig::getRemoveStringForEdgeType($type);
          return pht(
            $string,
            $this->renderHandleLink($author_phid),
            count($rem),
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s edited edge metadata.',
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

      default:
        return pht(
          '%s edited this %s.',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName());
    }
  }

  public function getTitleForFeed(PhabricatorFeedStory $story) {
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
        $type = $this->getMetadata('edge:type');
        $type = head($type);
        $string = PhabricatorEdgeConfig::getFeedStringForEdgeType($type);
        return pht(
          $string,
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case PhabricatorTransactions::TYPE_CUSTOMFIELD:
        $field = $this->getTransactionCustomField();
        if ($field) {
          return $field->getApplicationTransactionTitleForFeed($this, $story);
        } else {
          return pht(
            '%s edited a custom field on %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }

    }

    return $this->getTitle();
  }

  public function getBodyForFeed(PhabricatorFeedStory $story) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $body = null;

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $text = $this->getComment()->getContent();
        $body = phutil_escape_html_newlines(
          phutil_utf8_shorten($text, 128));
        break;
    }
    return $body;
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return 0.5;
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

    return $this->renderTextCorpusChangeDetails();
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
    assert_instances_of($group, 'PhabricatorApplicationTransaction');
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
    // TODO: (T603) Exact policies are unclear here.
    return null;
  }


}
