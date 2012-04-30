ALTER TABLE `{$NAMESPACE}_differential`.`differential_revision`
  ADD `lastReviewerPHID` varchar(64) BINARY AFTER `authorPHID`;

UPDATE `{$NAMESPACE}_differential`.`differential_revision`
SET `lastReviewerPHID` = (
  SELECT `authorPHID`
  FROM `{$NAMESPACE}_differential`.`differential_comment`
  WHERE `revisionID` = `differential_revision`.`id`
  AND `action` IN ('accept', 'reject')
  ORDER BY `id` DESC
  LIMIT 1
);
