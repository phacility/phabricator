<?php

final class PhabricatorProjectTransaction
  extends PhabricatorModularTransaction {

  const TYPE_PARENT = 'project:parent';
  const TYPE_MILESTONE = 'project:milestone';
  const TYPE_HASWORKBOARD = 'project:hasworkboard';
  const TYPE_DEFAULT_SORT = 'project:sort';
  const TYPE_DEFAULT_FILTER = 'project:filter';
  const TYPE_BACKGROUND = 'project:background';

  // NOTE: This is deprecated, members are just a normal edge now.
  const TYPE_MEMBERS    = 'project:members';

  const MAILTAG_METADATA    = 'project-metadata';
  const MAILTAG_MEMBERS     = 'project-members';
  const MAILTAG_WATCHERS    = 'project-watchers';
  const MAILTAG_OTHER       = 'project-other';

  public function getApplicationName() {
    return 'project';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProjectProjectPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorProjectTransactionType';
  }

  public function getRequiredHandlePHIDs() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $req_phids = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        $req_phids = array_merge($add, $rem);
        break;
    }

    return array_merge($req_phids, parent::getRequiredHandlePHIDs());
  }

  public function shouldHide() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $this->getMetadataValue('edge:type');
        switch ($edge_type) {
          case PhabricatorProjectSilencedEdgeType::EDGECONST:
            return true;
          default:
            break;
        }
    }

    return parent::shouldHide();
  }

  public function shouldHideForFeed() {
    switch ($this->getTransactionType()) {
      case self::TYPE_HASWORKBOARD:
      case self::TYPE_DEFAULT_SORT:
      case self::TYPE_DEFAULT_FILTER:
      case self::TYPE_BACKGROUND:
        return true;
    }

    return parent::shouldHideForFeed();
  }

  public function shouldHideForMail(array $xactions) {
    switch ($this->getTransactionType()) {
      case self::TYPE_HASWORKBOARD:
      case self::TYPE_DEFAULT_SORT:
      case self::TYPE_DEFAULT_FILTER:
      case self::TYPE_BACKGROUND:
        return true;
    }

    return parent::shouldHideForMail($xactions);
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_MEMBERS:
        return 'fa-user';
    }
    return parent::getIcon();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_phid = $this->getAuthorPHID();
    $author_handle = $this->renderHandleLink($author_phid);

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s created this project.',
          $this->renderHandleLink($author_phid));

      case self::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s changed project member(s), added %d: %s; removed %d: %s.',
            $author_handle,
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          if (count($add) == 1 && (head($add) == $this->getAuthorPHID())) {
            return pht(
              '%s joined this project.',
              $author_handle);
          } else {
            return pht(
              '%s added %d project member(s): %s.',
              $author_handle,
              count($add),
              $this->renderHandleList($add));
          }
        } else if ($rem) {
          if (count($rem) == 1 && (head($rem) == $this->getAuthorPHID())) {
            return pht(
              '%s left this project.',
              $author_handle);
          } else {
            return pht(
              '%s removed %d project member(s): %s.',
              $author_handle,
              count($rem),
              $this->renderHandleList($rem));
          }
        }
        break;

      case self::TYPE_HASWORKBOARD:
        if ($new) {
          return pht(
            '%s enabled the workboard for this project.',
            $author_handle);
        } else {
          return pht(
            '%s disabled the workboard for this project.',
            $author_handle);
        }

      case self::TYPE_DEFAULT_SORT:
        return pht(
          '%s changed the default sort order for the project workboard.',
          $author_handle);

      case self::TYPE_DEFAULT_FILTER:
        return pht(
          '%s changed the default filter for the project workboard.',
          $author_handle);

      case self::TYPE_BACKGROUND:
        return pht(
          '%s changed the background color of the project workboard.',
          $author_handle);
    }

    return parent::getTitle();
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case PhabricatorProjectNameTransaction::TRANSACTIONTYPE:
      case PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE:
      case PhabricatorProjectImageTransaction::TRANSACTIONTYPE:
      case PhabricatorProjectIconTransaction::TRANSACTIONTYPE:
      case PhabricatorProjectColorTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_METADATA;
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $type = $this->getMetadata('edge:type');
        $type = head($type);
        $type_member = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;
        $type_watcher = PhabricatorObjectHasWatcherEdgeType::EDGECONST;
        if ($type == $type_member) {
          $tags[] = self::MAILTAG_MEMBERS;
        } else if ($type == $type_watcher) {
          $tags[] = self::MAILTAG_WATCHERS;
        } else {
          $tags[] = self::MAILTAG_OTHER;
        }
        break;
      case PhabricatorProjectStatusTransaction::TRANSACTIONTYPE:
      case PhabricatorProjectLockTransaction::TRANSACTIONTYPE:
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
