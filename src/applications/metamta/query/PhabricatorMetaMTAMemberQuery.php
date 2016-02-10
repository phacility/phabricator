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
            ->needMembers(true)
            ->needWatchers(true)
            ->execute();

          $edge_type = PhabricatorProjectSilencedEdgeType::EDGECONST;

          $edge_query = id(new PhabricatorEdgeQuery())
            ->withSourcePHIDs($phids)
            ->withEdgeTypes(
              array(
                $edge_type,
              ));

          $edge_query->execute();

          $projects = mpull($projects, null, 'getPHID');
          foreach ($phids as $phid) {
            $project = idx($projects, $phid);

            if (!$project) {
              $results[$phid] = array();
              continue;
            }

            // Recipients are members who haven't silenced the project, plus
            // watchers.

            $members = $project->getMemberPHIDs();
            $members = array_fuse($members);

            $watchers = $project->getWatcherPHIDs();
            $watchers = array_fuse($watchers);

            $silenced = $edge_query->getDestinationPHIDs(
              array($phid),
              array($edge_type));
            $silenced = array_fuse($silenced);

            $result_map = array_diff_key($members, $silenced);
            $result_map = $result_map + $watchers;

            $results[$phid] = array_values($result_map);
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
