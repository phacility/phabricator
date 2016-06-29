<?php

final class DiffusionLowLevelCommitFieldsQuery
  extends DiffusionLowLevelQuery {

  private $ref;
  private $revisionMatchData = array(
    'usedURI' => null,
    'foundURI' => null,
    'validDomain' => null,
    'matchHashType' => null,
    'matchHashValue' => null,
  );

  public function withCommitRef(DiffusionCommitRef $ref) {
    $this->ref = $ref;
    return $this;
  }

  public function getRevisionMatchData() {
    return $this->revisionMatchData;
  }

  private function setRevisionMatchData($key, $value) {
    $this->revisionMatchData[$key] = $value;
    return $this;
  }

  protected function executeQuery() {
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

    $revision_id = idx($fields, 'revisionID');
    if ($revision_id) {
      $this->setRevisionMatchData('usedURI', true);
    } else {
      $this->setRevisionMatchData('usedURI', false);
    }
    $revision_id_info = $result['revisionIDFieldInfo'];
    $this->setRevisionMatchData('foundURI', $revision_id_info['value']);
    $this->setRevisionMatchData(
      'validDomain',
      $revision_id_info['validDomain']);

    // If there is no "Differential Revision:" field in the message, try to
    // identify the revision by doing a hash lookup.

    if (!$revision_id && $hashes) {
      $hash_list = array();
      foreach ($hashes as $hash) {
        $hash_list[] = array($hash->getHashType(), $hash->getHashValue());
      }
      $revisions = id(new DifferentialRevisionQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->needHashes(true)
        ->withCommitHashes($hash_list)
        ->execute();

      if ($revisions) {
        $revision = $this->pickBestRevision($revisions);

        $fields['revisionID'] = $revision->getID();
        $revision_hashes = $revision->getHashes();

        $revision_hashes = DiffusionCommitHash::convertArrayToObjects(
          $revision_hashes);
        $revision_hashes = mpull($revision_hashes, null, 'getHashType');

        // sort the hashes in the order the mighty
        // @{class:ArcanstDifferentialRevisionHash} does; probably unnecessary
        // but should future proof things nicely.
        $revision_hashes = array_select_keys(
          $revision_hashes,
          ArcanistDifferentialRevisionHash::getTypes());

        foreach ($hashes as $hash) {
          $revision_hash = idx($revision_hashes, $hash->getHashType());
          if (!$revision_hash) {
            continue;
          }
          if ($revision_hash->getHashValue() == $hash->getHashValue()) {
            $this->setRevisionMatchData(
              'matchHashType',
              $hash->getHashType());
            $this->setRevisionMatchData(
              'matchHashValue',
              $hash->getHashValue());
            break;
          }
        }
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
