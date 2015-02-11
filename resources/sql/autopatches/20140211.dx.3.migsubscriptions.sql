/* For `grep`: */

/* PhabricatorObjectHasSubscriberEdgeType::EDGECONST = 21 */

INSERT IGNORE INTO {$NAMESPACE}_differential.edge (src, type, dst, seq)
  SELECT rev.phid, 21, rel.objectPHID, rel.sequence
    FROM {$NAMESPACE}_differential.differential_revision rev
    JOIN {$NAMESPACE}_differential.differential_relationship rel
      ON rev.id = rel.revisionID
    WHERE relation = 'subd';
