UPDATE {$NAMESPACE}_legalpad.legalpad_documentsignature
  SET signerPHID = NULL WHERE signerPHID LIKE 'PHID-XUSR-%';
