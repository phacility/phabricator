<?php

final class PhabricatorProjectsWatchersSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Project Watchers');
  }

  public function getAttachmentDescription() {
    return pht('Get the watcher list for the project.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needWatchers(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $watchers = array();
    foreach ($object->getWatcherPHIDs() as $watcher_phid) {
      $watchers[] = array(
        'phid' => $watcher_phid,
      );
    }

    return array(
      'watchers' => $watchers,
    );
  }

}
