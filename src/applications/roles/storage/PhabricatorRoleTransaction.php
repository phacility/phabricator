<?php

final class PhabricatorRoleTransaction
  extends PhabricatorModularTransaction {

  // NOTE: This is deprecated, members are just a normal edge now.
  const TYPE_MEMBERS    = 'role:members';

  const MAILTAG_METADATA    = 'role-metadata';
  const MAILTAG_MEMBERS     = 'role-members';
  const MAILTAG_WATCHERS    = 'role-watchers';
  const MAILTAG_OTHER       = 'role-other';

  public function getApplicationName() {
    return 'role';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRoleRolePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorRoleTransactionType';
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
          case PhabricatorRoleSilencedEdgeType::EDGECONST:
            return true;
          default:
            break;
        }
    }

    return parent::shouldHide();
  }

  public function shouldHideForMail(array $xactions) {
    switch ($this->getTransactionType()) {
      case PhabricatorRoleWorkboardTransaction::TRANSACTIONTYPE:
      case PhabricatorRoleSortTransaction::TRANSACTIONTYPE:
      case PhabricatorRoleFilterTransaction::TRANSACTIONTYPE:
      case PhabricatorRoleWorkboardBackgroundTransaction::TRANSACTIONTYPE:
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
          '%s created this role.',
          $this->renderHandleLink($author_phid));

      case self::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s changed role member(s), added %d: %s; removed %d: %s.',
            $author_handle,
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          if (count($add) == 1 && (head($add) == $this->getAuthorPHID())) {
            return pht(
              '%s joined this role.',
              $author_handle);
          } else {
            return pht(
              '%s added %d role member(s): %s.',
              $author_handle,
              count($add),
              $this->renderHandleList($add));
          }
        } else if ($rem) {
          if (count($rem) == 1 && (head($rem) == $this->getAuthorPHID())) {
            return pht(
              '%s left this role.',
              $author_handle);
          } else {
            return pht(
              '%s removed %d role member(s): %s.',
              $author_handle,
              count($rem),
              $this->renderHandleList($rem));
          }
        }
        break;
    }

    return parent::getTitle();
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case PhabricatorRoleNameTransaction::TRANSACTIONTYPE:
      case PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE:
      case PhabricatorRoleImageTransaction::TRANSACTIONTYPE:
      case PhabricatorRoleIconTransaction::TRANSACTIONTYPE:
      case PhabricatorRoleColorTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_METADATA;
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $type = $this->getMetadata('edge:type');
        $type = head($type);
        $type_member = PhabricatorRoleRoleHasMemberEdgeType::EDGECONST;
        $type_watcher = PhabricatorObjectHasWatcherEdgeType::EDGECONST;
        if ($type == $type_member) {
          $tags[] = self::MAILTAG_MEMBERS;
        } else if ($type == $type_watcher) {
          $tags[] = self::MAILTAG_WATCHERS;
        } else {
          $tags[] = self::MAILTAG_OTHER;
        }
        break;
      case PhabricatorRoleStatusTransaction::TRANSACTIONTYPE:
      case PhabricatorRoleLockTransaction::TRANSACTIONTYPE:
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
