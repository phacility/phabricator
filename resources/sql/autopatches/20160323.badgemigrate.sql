/* PhabricatorBadgeHasRecipientEdgeType::TYPECONST = 59 */

INSERT IGNORE INTO {$NAMESPACE}_badges.badges_award
  (badgePHID, recipientPHID, awarderPHID, dateCreated, dateModified)
  SELECT src, dst, 'PHID-VOID-00000000000000000000', dateCreated, dateCreated
    FROM {$NAMESPACE}_badges.edge WHERE type = 59;
