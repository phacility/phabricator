/* PhabricatorProjectObjectHasProjectEdgeType::EDGECONST = 41 */
/* PhabricatorProjectProjectHasObjectEdgeType::EDGECONST = 42 */

INSERT IGNORE INTO {$NAMESPACE}_maniphest.edge (src, type, dst)
  SELECT taskPHID, 41, projectPHID
  FROM {$NAMESPACE}_maniphest.maniphest_taskproject;

INSERT IGNORE INTO {$NAMESPACE}_project.edge (src, type, dst)
  SELECT projectPHID, 42, taskPHID
  FROM {$NAMESPACE}_maniphest.maniphest_taskproject;
