<?php

final class PhabricatorProjectWatcherListView
  extends PhabricatorProjectUserListView {

  protected function canEditList() {
    return false;
  }

  protected function getNoDataString() {
    return pht('This project does not have any watchers.');
  }

  protected function getRemoveURI($phid) {
    return null;
  }

  protected function getHeaderText() {
    return pht('Watchers');
  }

}
