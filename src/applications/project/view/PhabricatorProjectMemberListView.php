<?php

final class PhabricatorProjectMemberListView
  extends PhabricatorProjectUserListView {

  protected function canEditList() {
    $viewer = $this->getViewer();
    $project = $this->getProject();

    if (!$project->supportsEditMembers()) {
      return false;
    }

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  protected function getNoDataString() {
    return pht('This project does not have any members.');
  }

  protected function getRemoveURI($phid) {
    $project = $this->getProject();
    $id = $project->getID();
    return "/project/members/{$id}/remove/?phid={$phid}";
  }

  protected function getHeaderText() {
    return pht('Members');
  }

  protected function getMembershipNote() {
    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();
    $project = $this->getProject();

    if (!$viewer_phid) {
      return null;
    }

    $note = null;
    if ($project->isUserMember($viewer_phid)) {
      $edge_type = PhabricatorProjectSilencedEdgeType::EDGECONST;
      $silenced = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $project->getPHID(),
        $edge_type);
      $silenced = array_fuse($silenced);
      $is_silenced = isset($silenced[$viewer_phid]);
      if ($is_silenced) {
        $note = pht(
          'You have disabled mail. When mail is sent to project members, '.
          'you will not receive a copy.');
      } else {
        $note = pht(
          'You are a member and you will receive mail that is sent to all '.
          'project members.');
      }
    }

    return $note;
  }

}
