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
    $viewer = $this->getViewer();

    $phids = array_fuse($this->phids);
    $actors = array();
    $type_map = array();
    foreach ($phids as $phid) {
      $type_map[phid_get_type($phid)][] = $phid;
    }

    // TODO: Generalize this somewhere else.


    // If we have packages, break them down into their constituent user and
    // project owners first. Then we'll resolve those and build the packages
    // back up from the pieces.
    $package_type = PhabricatorOwnersPackagePHIDType::TYPECONST;
    $package_phids = idx($type_map, $package_type, array());
    unset($type_map[$package_type]);

    $package_map = array();
    if ($package_phids) {
      $packages = id(new PhabricatorOwnersPackageQuery())
        ->setViewer($viewer)
        ->withPHIDs($package_phids)
        ->execute();

      foreach ($packages as $package) {
        $package_owners = array();
        foreach ($package->getOwners() as $owner) {
          $owner_phid = $owner->getUserPHID();
          $owner_type = phid_get_type($owner_phid);
          $type_map[$owner_type][] = $owner_phid;
          $package_owners[] = $owner_phid;
        }
        $package_map[$package->getPHID()] = $package_owners;
      }
    }

    $results = array();
    foreach ($type_map as $type => $phids) {
      switch ($type) {
        case PhabricatorProjectProjectPHIDType::TYPECONST:
          // NOTE: We're loading the projects here in order to respect policies.

          $projects = id(new PhabricatorProjectQuery())
            ->setViewer($viewer)
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

    // For any packages, stitch them back together from the resolved users
    // and projects.
    if ($package_map) {
      foreach ($package_map as $package_phid => $owner_phids) {
        $resolved = array();
        foreach ($owner_phids as $owner_phid) {
          $resolved_phids = idx($results, $owner_phid, array());
          foreach ($resolved_phids as $resolved_phid) {
            $resolved[] = $resolved_phid;
          }
        }
        $results[$package_phid] = $resolved;
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
