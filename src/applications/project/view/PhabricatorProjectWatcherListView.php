<?php

final class PhabricatorProjectWatcherListView
  extends PhabricatorProjectUserListView {

  protected function canEditList() {
    $viewer = $this->getUser();
    $project = $this->getProject();

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  protected function getNoDataString() {
    return pht('This project does not have any watchers.');
  }

  protected function getRemoveURI($phid) {
    $project = $this->getProject();
    $id = $project->getID();
    return "/project/watchers/{$id}/remove/?phid={$phid}";
  }

  protected function getHeaderText() {
    return pht('Watchers');
  }

}
