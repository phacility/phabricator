<?php

final class ManiphestTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_STATUS = 'maniphest-status';
  const MAILTAG_OWNER = 'maniphest-owner';
  const MAILTAG_PRIORITY = 'maniphest-priority';
  const MAILTAG_CC = 'maniphest-cc';
  const MAILTAG_PROJECTS = 'maniphest-projects';
  const MAILTAG_COMMENT = 'maniphest-comment';
  const MAILTAG_COLUMN = 'maniphest-column';
  const MAILTAG_UNBLOCK = 'maniphest-unblock';
  const MAILTAG_OTHER = 'maniphest-other';


  public function getApplicationName() {
    return 'maniphest';
  }

  public function getApplicationTransactionType() {
    return ManiphestTaskPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new ManiphestTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'ManiphestTaskTransactionType';
  }

  public function shouldGenerateOldValue() {
    switch ($this->getTransactionType()) {
      case ManiphestTaskEdgeTransaction::TRANSACTIONTYPE:
      case ManiphestTaskUnblockTransaction::TRANSACTIONTYPE:
        return false;
    }

    return parent::shouldGenerateOldValue();
  }

  public function shouldHideForFeed() {
    // NOTE: Modular transactions don't currently support this, and it has
    // very few callsites, and it's publish-time rather than display-time.
    // This should probably become a supported, display-time behavior. For
    // discussion, see T12787.

    // Hide "alice created X, a task blocking Y." from feed because it
    // will almost always appear adjacent to "alice created Y".
    $is_new = $this->getMetadataValue('blocker.new');
    if ($is_new) {
      return true;
    }

    return parent::shouldHideForFeed();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $new = $this->getNewValue();
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
        if ($new) {
          $phids[] = $new;
        }

        if ($old) {
          $phids[] = $old;
        }
        break;
      case ManiphestTaskMergedIntoTransaction::TRANSACTIONTYPE:
        $phids[] = $new;
        break;
      case ManiphestTaskMergedFromTransaction::TRANSACTIONTYPE:
        $phids = array_merge($phids, $new);
        break;
      case ManiphestTaskEdgeTransaction::TRANSACTIONTYPE:
        $phids = array_mergev(
          array(
            $phids,
            array_keys(nonempty($old, array())),
            array_keys(nonempty($new, array())),
          ));
        break;
      case ManiphestTaskAttachTransaction::TRANSACTIONTYPE:
        $old = nonempty($old, array());
        $new = nonempty($new, array());
        $phids = array_mergev(
          array(
            $phids,
            array_keys(idx($new, 'FILE', array())),
            array_keys(idx($old, 'FILE', array())),
          ));
        break;
      case ManiphestTaskUnblockTransaction::TRANSACTIONTYPE:
        foreach (array_keys($new) as $phid) {
          $phids[] = $phid;
        }
        break;
      case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
        $commit_phid = $this->getMetadataValue('commitPHID');
        if ($commit_phid) {
          $phids[] = $commit_phid;
        }
        break;
    }

    return $phids;
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COLUMNS:
        return pht('Changed Project Column');
    }

    return parent::getActionName();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COLUMNS:
        return 'fa-columns';
    }

    return parent::getIcon();
  }


  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBTYPE:
        return pht(
          '%s changed the subtype of this task from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderSubtypeName($old),
          $this->renderSubtypeName($new));
        break;
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBTYPE:
        return pht(
          '%s changed the subtype of %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $this->renderSubtypeName($old),
          $this->renderSubtypeName($new));
    }

    return parent::getTitleForFeed();
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case ManiphestTaskMergedIntoTransaction::TRANSACTIONTYPE:
      case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_STATUS;
        break;
      case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_OWNER;
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $tags[] = self::MAILTAG_CC;
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($this->getMetadataValue('edge:type')) {
          case PhabricatorProjectObjectHasProjectEdgeType::EDGECONST:
            $tags[] = self::MAILTAG_PROJECTS;
            break;
          default:
            $tags[] = self::MAILTAG_OTHER;
            break;
        }
        break;
      case ManiphestTaskPriorityTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_PRIORITY;
        break;
      case ManiphestTaskUnblockTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_UNBLOCK;
        break;
      case PhabricatorTransactions::TYPE_COLUMNS:
        $tags[] = self::MAILTAG_COLUMN;
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

  public function getNoEffectDescription() {
    switch ($this->getTransactionType()) {
      case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
        return pht('The task already has the selected status.');
      case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
        return pht('The task already has the selected owner.');
      case ManiphestTaskPriorityTransaction::TRANSACTIONTYPE:
        return pht('The task already has the selected priority.');
    }

    return parent::getNoEffectDescription();
  }

  public function renderSubtypeName($value) {
    $object = $this->getObject();
    $map = $object->newEditEngineSubtypeMap();
    if (!isset($map[$value])) {
      return $value;
    }

    return $map[$value]->getName();
  }

}
