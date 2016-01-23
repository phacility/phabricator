/* PhabricatorObjectHasUnsubscriberEdgeType::EDGECONST = 23 */
/* PhabricatorProjectSilencedEdgeType::EDGECONST = 61 */

/* This is converting existing unsubscribes into disabled mail. */

INSERT IGNORE INTO {$NAMESPACE}_project.edge (src, type, dst, dateCreated)
  SELECT src, 61, dst, dateCreated FROM {$NAMESPACE}_project.edge
  WHERE type = 23;
