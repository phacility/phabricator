<?php

final class PhabricatorRoleWatcherListView
  extends PhabricatorRoleUserListView {

  protected function canEditList() {
    $viewer = $this->getViewer();
    $role = $this->getRole();

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  protected function getNoDataString() {
    return pht('This role does not have any watchers.');
  }

  protected function getRemoveURI($phid) {
    $role = $this->getRole();
    $id = $role->getID();
    return "/role/watchers/{$id}/remove/?phid={$phid}";
  }

  protected function getHeaderText() {
    return pht('Watchers');
  }

  protected function getMembershipNote() {
    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();
    $role = $this->getRole();

    $note = null;
    if ($role->isUserWatcher($viewer_phid)) {
      $note = pht('You are watching this role and will receive mail about '.
                  'changes made to any related object.');
    }
    return $note;
  }

}
