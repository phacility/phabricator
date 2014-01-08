<?php

final class DiffusionLowLevelCommitFieldsQuery
  extends DiffusionLowLevelQuery {

  private $ref;

  public function withCommitRef(DiffusionCommitRef $ref) {
    $this->ref = $ref;
    return $this;
  }

  public function executeQuery() {
    $ref = $this->ref;
    $message = $ref->getMessage();
    $hashes = $ref->getHashes();

    $params = array(
      'corpus' => $message,
      'partial' => true,
    );

    $result = id(new ConduitCall('differential.parsecommitmessage', $params))
      ->setUser(PhabricatorUser::getOmnipotentUser())
      ->execute();
    $fields = $result['fields'];

    // If there is no "Differential Revision:" field in the message, try to
    // identify the revision by doing a hash lookup.

    $revision_id = idx($fields, 'revisionID');
    if (!$revision_id && $hashes) {
      $hash_list = array();
      foreach ($hashes as $hash) {
        $hash_list[] = array($hash->getHashType(), $hash->getHashValue());
      }
      $revisions = id(new DifferentialRevisionQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withCommitHashes($hash_list)
        ->execute();

      if (!empty($revisions)) {
        $revision = $this->pickBestRevision($revisions);
        $fields['revisionID'] = $revision->getID();
      }
    }

    return $fields;
  }


  /**
   * When querying for revisions by hash, more than one revision may be found.
   * This function identifies the "best" revision from such a set. Typically,
   * there is only one revision found. Otherwise, we try to pick an accepted
   * revision first, followed by an open revision, and otherwise we go with a
   * closed or abandoned revision as a last resort.
   */
  private function pickBestRevision(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');

    // If we have more than one revision of a given status, choose the most
    // recently updated one.
    $revisions = msort($revisions, 'getDateModified');
    $revisions = array_reverse($revisions);

    // Try to find an accepted revision first.
    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
    foreach ($revisions as $revision) {
      if ($revision->getStatus() == $status_accepted) {
        return $revision;
      }
    }

    // Try to find an open revision.
    foreach ($revisions as $revision) {
      if (!$revision->isClosed()) {
        return $revision;
      }
    }

    // Settle for whatever's left.
    return head($revisions);
  }

}
