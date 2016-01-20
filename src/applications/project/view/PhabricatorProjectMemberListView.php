<?php

final class PhabricatorProjectMemberListView
  extends PhabricatorProjectUserListView {

  protected function canEditList() {
    $viewer = $this->getUser();
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

}
