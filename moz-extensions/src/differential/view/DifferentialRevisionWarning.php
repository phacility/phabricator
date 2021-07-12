<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.
#
# This Source Code Form is "Incompatible With Secondary Licenses", as
# defined by the Mozilla Public License, v. 2.0.

class DifferentialRevisionWarning extends Phobject {
  private $warnings = array();

  public function createWarnings($viewer, $revision) {
    $this->warnings[] = $this->createSecurityWarning($viewer, $revision);
    $this->warnings[] = $this->createPublicDependencyWarning($viewer, $revision);
    return $this->warnings;
  }

  public function createSecurityWarning($viewer, $revision) {
    if (!isRevisionPrivate($viewer, $revision)) {
      return null;
    }
    $warning = new PHUIInfoView();
    $warning->setTitle(pht('This is a secure revision.'));
    $warning->setSeverity(PHUIInfoView::SEVERITY_WARNING);
    $warning->appendChild(hsprintf(pht(
      'Please use the CC list of the associated bug in Bugzilla to manage access and ' .
      'subscribership of this revision. Changes made here may be overwritten.<br/>' .
      'Please do not land this revision using `arc land` to prevent data leakage.')));
    return $warning->render();
  }

  /**
   * Display a warning for a secure revision which has a public dependency
   */
  public function createPublicDependencyWarning($viewer, $revision) {
    if (!isRevisionPrivate($viewer, $revision)) {
      return null;
    }

    // Find depending revisions.
    $stack_graph = id(new DifferentialRevisionGraph())
      ->setViewer($viewer)
      ->setSeedPHID($revision->getPHID())
      ->setLoadEntireGraph(true)
      ->loadGraph();

    if ($stack_graph->isEmpty()) {
      return null;
    }

    $stack_graph->newGraphTable();
    $parent_type = DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST;
    $reachable = $stack_graph->getReachableObjects($parent_type);

    // Remove depending revisions which are closed or private
    foreach ($reachable as $key => $reachable_revision) {
      if ($reachable_revision->isClosed() or isRevisionPrivate($viewer, $reachable_revision)) {
        unset($reachable[$key]);
      }
    }

    if (!$reachable) {
      return null;
    }

    $plural = '';
    if (count($reachable) > 1) {
      $plural = 's';
    }
    $warning = new PHUIInfoView();
    $warning->setTitle(pht(
      'A public revision%s is depending on this secure revision.',
      $plural));
    $warning->setSeverity(PHUIInfoView::SEVERITY_WARNING);
    $warning->appendChild(hsprintf(pht(
      'Please change the child revision%s to private by setting the security flag in Bugzilla.',
      $plural)));
    return $warning->render();
  }
}

function isRevisionPrivate($viewer, $revision) {
    $revision_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);

    if (!(bool)$revision_projects) {
      return null;
    }

    // Load a secure-revision project
    // TODO cache the result
    $secure_group = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withNames(array('secure-revision'))
      ->executeOne();

    return in_array($secure_group->getPHID(), $revision_projects);
}
