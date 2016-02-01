<?php

final class PhabricatorProjectsMembershipIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

  const EXTENSIONKEY = 'project.members';

  public function getExtensionName() {
    return pht('Project Members');
  }

  public function shouldIndexObject($object) {
    if (!($object instanceof PhabricatorProject)) {
      return false;
    }

    return true;
  }

  public function indexObject(
    PhabricatorIndexEngine $engine,
    $object) {

    $this->rematerialize($object);
  }

  public function rematerialize(PhabricatorProject $project) {
    $materialize = $project->getAncestorProjects();
    array_unshift($materialize, $project);

    foreach ($materialize as $project) {
      $this->materializeProject($project);
    }
  }

  private function materializeProject(PhabricatorProject $project) {
    if ($project->isMilestone()) {
      return;
    }

    $material_type = PhabricatorProjectMaterializedMemberEdgeType::EDGECONST;
    $member_type = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

    $project_phid = $project->getPHID();

    $descendants = id(new PhabricatorProjectQuery())
      ->setViewer($this->getViewer())
      ->withAncestorProjectPHIDs(array($project->getPHID()))
      ->withIsMilestone(false)
      ->withHasSubprojects(false)
      ->execute();
    $descendant_phids = mpull($descendants, 'getPHID');

    if ($descendant_phids) {
      $source_phids = $descendant_phids;
      $has_subprojects = true;
    } else {
      $source_phids = array($project->getPHID());
      $has_subprojects = false;
    }

    $conn_w = $project->establishConnection('w');

    $any_milestone = queryfx_one(
      $conn_w,
      'SELECT id FROM %T
        WHERE parentProjectPHID = %s AND milestoneNumber IS NOT NULL
        LIMIT 1',
      $project->getTableName(),
      $project_phid);
    $has_milestones = (bool)$any_milestone;

    $project->openTransaction();

      // Delete any existing materialized member edges.
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE src = %s AND type = %s',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        $project_phid,
        $material_type);

      // Copy current member edges to create new materialized edges.
      queryfx(
        $conn_w,
        'INSERT IGNORE INTO %T (src, type, dst, dateCreated, seq)
          SELECT %s, %d, dst, dateCreated, seq FROM %T
          WHERE src IN (%Ls) AND type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        $project_phid,
        $material_type,
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        $source_phids,
        $member_type);

      // Update the hasSubprojects flag.
      queryfx(
        $conn_w,
        'UPDATE %T SET hasSubprojects = %d WHERE id = %d',
        $project->getTableName(),
        (int)$has_subprojects,
        $project->getID());

      // Update the hasMilestones flag.
      queryfx(
        $conn_w,
        'UPDATE %T SET hasMilestones = %d WHERE id = %d',
        $project->getTableName(),
        (int)$has_milestones,
        $project->getID());

    $project->saveTransaction();
  }

}
