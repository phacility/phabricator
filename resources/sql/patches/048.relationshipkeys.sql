ALTER TABLE {$NAMESPACE}_search.search_documentrelationship
  add key (relatedPHID, relation);

ALTER TABLE {$NAMESPACE}_search.search_documentrelationship
  add key (relation, relatedPHID);
