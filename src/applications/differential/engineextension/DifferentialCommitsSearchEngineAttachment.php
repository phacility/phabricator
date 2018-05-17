<?php

final class DifferentialCommitsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Diff Commits');
  }

  public function getAttachmentDescription() {
    return pht('Get the local commits (if any) for each diff.');
  }

  public function loadAttachmentData(array $objects, $spec) {
    $properties = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID IN (%Ld) AND name = %s',
      mpull($objects, 'getID'),
      'local:commits');

    $map = array();
    foreach ($properties as $property) {
      $map[$property->getDiffID()] = $property->getData();
    }

    return $map;
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $diff_id = $object->getID();
    $info = idx($data, $diff_id, array());

    // NOTE: This should be similar to the information returned about commits
    // by "diffusion.commit.search".

    $list = array();
    foreach ($info as $commit) {
      $author_epoch = idx($commit, 'time');
      if ($author_epoch) {
        $author_epoch = (int)$author_epoch;
      }

      // TODO: Currently, we don't upload the raw author string from "arc".
      // Reconstruct a plausible version of it until we begin uploading this
      // information.

      $author_name = idx($commit, 'author');
      $author_email = idx($commit, 'authorEmail');
      if (strlen($author_name) && strlen($author_email)) {
        $author_raw = (string)id(new PhutilEmailAddress())
          ->setDisplayName($author_name)
          ->setAddress($author_email);
      } else if (strlen($author_email)) {
        $author_raw = $author_email;
      } else {
        $author_raw = $author_name;
      }

      $list[] = array(
        'identifier' => $commit['commit'],
        'tree' => idx($commit, 'tree'),
        'parents' => idx($commit, 'parents', array()),
        'author' => array(
          'name' => $author_name,
          'email' => $author_email,
          'raw' => $author_raw,
          'epoch' => $author_epoch,
        ),
        'message' => idx($commit, 'message'),
      );
    }

    return array(
      'commits' => $list,
    );
  }

}
