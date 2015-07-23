<?php

/**
 * Expands aggregate mail recipients into their component mailables. For
 * example, a project currently expands into all of its members.
 */
final class PhabricatorMetaMTAMemberQuery extends PhabricatorQuery {

  private $phids = array();
  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function execute() {
    $phids = array_fuse($this->phids);
    $actors = array();
    $type_map = array();
    foreach ($phids as $phid) {
      $type_map[phid_get_type($phid)][] = $phid;
    }

    // TODO: Generalize this somewhere else.

    $results = array();
    foreach ($type_map as $type => $phids) {
      switch ($type) {
        case PhabricatorProjectProjectPHIDType::TYPECONST:
          // NOTE: We're loading the projects here in order to respect policies.

          $projects = id(new PhabricatorProjectQuery())
            ->setViewer($this->getViewer())
            ->withPHIDs($phids)
            ->execute();

          $subscribers = id(new PhabricatorSubscribersQuery())
            ->withObjectPHIDs($phids)
            ->execute();

          $projects = mpull($projects, null, 'getPHID');
          foreach ($phids as $phid) {
            $project = idx($projects, $phid);
            if (!$project) {
              $results[$phid] = array();
            } else {
              $results[$phid] = idx($subscribers, $phid, array());
            }
          }
          break;
        default:
          // For other types, just map the PHID to itself without modification.
          // This allows callers to do less work.
          foreach ($phids as $phid) {
            $results[$phid] = array($phid);
          }
          break;
      }
    }

    return $results;
  }


  /**
   * Execute the query, merging results into a single list of unique member
   * PHIDs.
   */
  public function executeExpansion() {
    return array_unique(array_mergev($this->execute()));
  }

}
