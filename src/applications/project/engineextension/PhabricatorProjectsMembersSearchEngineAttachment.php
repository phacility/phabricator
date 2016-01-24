<?php

final class PhabricatorProjectsMembersSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Project Members');
  }

  public function getAttachmentDescription() {
    return pht('Get the member list for the project.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needMembers(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $members = array();
    foreach ($object->getMemberPHIDs() as $member_phid) {
      $members[] = array(
        'phid' => $member_phid,
      );
    }

    return array(
      'members' => $members,
    );
  }

}
