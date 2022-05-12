INSERT IGNORE INTO {$NAMESPACE}_file.file_attachment
  (objectPHID, filePHID, attachmentMode, attacherPHID,
    dateCreated, dateModified)
  SELECT dst, src, 'attach', null, dateCreated, dateCreated
    FROM {$NAMESPACE}_file.edge
    WHERE type = 26
    ORDER BY dateCreated ASC;
