UPDATE {$NAMESPACE}_legalpad.legalpad_document
  SET signatureType = 'user' WHERE signatureType = '';
