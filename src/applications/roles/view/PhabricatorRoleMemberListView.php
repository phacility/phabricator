<?php

final class PhabricatorRoleMemberListView
  extends PhabricatorRoleUserListView {

  protected function canEditList() {
    $viewer = $this->getViewer();
    $role = $this->getRole();

    if (!$role->supportsEditMembers()) {
      return false;
    }

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  protected function getNoDataString() {
    return pht('This role does not have any members.');
  }

  protected function getRemoveURI($phid) {
    $role = $this->getRole();
    $id = $role->getID();
    return "/role/members/{$id}/remove/?phid={$phid}";
  }

  protected function getHeaderText() {
    return pht('Members');
  }

  protected function getMembershipNote() {
    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();
    $role = $this->getRole();

    if (!$viewer_phid) {
      return null;
    }

    $note = null;
    if ($role->isUserMember($viewer_phid)) {
      $edge_type = PhabricatorRoleSilencedEdgeType::EDGECONST;
      $silenced = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $role->getPHID(),
        $edge_type);
      $silenced = array_fuse($silenced);
      $is_silenced = isset($silenced[$viewer_phid]);
      if ($is_silenced) {
        $note = pht(
          'You have disabled mail. When mail is sent to role members, '.
          'you will not receive a copy.');
      } else {
        $note = pht(
          'You are a member and you will receive mail that is sent to all '.
          'role members.');
      }
    }

    return $note;
  }

}
