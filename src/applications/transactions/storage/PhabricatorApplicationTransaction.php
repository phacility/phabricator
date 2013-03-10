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

  protected $contentSource;

  private $comment;
  private $commentNotLoaded;

  private $handles;
  private $renderingTarget = self::TARGET_HTML;
  private $transactionGroup = array();

  abstract public function getApplicationTransactionType();
  abstract public function getApplicationTransactionCommentObject();
  abstract public function getApplicationObjectTypeName();

  public function getApplicationTransactionViewObject() {
    return new PhabricatorApplicationTransactionView();
  }

  public function generatePHID() {
    $type = PhabricatorPHIDConstants::PHID_TYPE_XACT;
    $subtype = $this->getApplicationTransactionType();

    return PhabricatorPHID::generateNewPHID($type, $subtype);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'oldValue' => self::SERIALIZATION_JSON,
        'newValue' => self::SERIALIZATION_JSON,
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

/* -(  Rendering  )---------------------------------------------------------- */

  public function setRenderingTarget($rendering_target) {
    $this->renderingTarget = $rendering_target;
    return $this;
  }

  public function getRenderingTarget() {
    return $this->renderingTarget;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $phids[] = array($this->getAuthorPHID());
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $phids[] = $old;
        $phids[] = $new;
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
        "Transaction requires a handle ('{$phid}') it did not load.");
    }
    return $this->handles[$phid];
  }

  public function getHandles() {
    if ($this->handles === null) {
      throw new Exception(
        'Transaction requires handles and it did not load them.'
      );
    }
    return $this->handles;
  }

  protected function renderHandleLink($phid) {
    if ($this->renderingTarget == self::TARGET_HTML) {
      return $this->getHandle($phid)->renderLink();
    } else {
      return hsprintf('%s', $this->getHandle($phid)->getName());
    }
  }

  protected function renderHandleList(array $phids) {
    $links = array();
    foreach ($phids as $phid) {
      $links[] = $this->renderHandleLink($phid);
    }
    return phutil_implode_html(', ', $links);
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return 'comment';
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return 'message';
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return 'lock';
    }

    return null;
  }

  public function getColor() {
    return null;
  }

  public function shouldHide() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        if ($this->getOldValue() === null) {
          return true;
        } else {
          return false;
        }
        break;
    }

    return false;
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
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return pht(
          'All users are already subscribed to this %s.',
          $this->getApplicationObjectTypeName());
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
        // TODO: Render human-readable.
        return pht(
          '%s changed the visibility of this %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName(),
          $old,
          $new);
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        // TODO: Render human-readable.
        return pht(
          '%s changed the edit policy of this %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->getApplicationObjectTypeName(),
          $old,
          $new);
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
        } else {
          return pht(
            '%s removed %d subscriber(s): %s.',
            $this->renderHandleLink($author_phid),
            count($rem),
            $this->renderHandleList($rem));
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
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return pht(
          '%s updated subscribers of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
    }

    return $this->getTitle();
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return 0.5;
    }
    return 1.0;
  }

  public function getActionName() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return pht('Commented On');
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
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
    return false;
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    return null;
  }

  public function attachTransactionGroup(array $group) {
    assert_instances_of($group, 'PhabricatorApplicationTransaction');
    $this->transactionGroup = $group;
    return $this;
  }

  public function getTransactionGroup() {
    return $this->transactionGroup;
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

}
