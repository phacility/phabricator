ALTER TABLE phabricator_search.search_documentrelationship
  add key (relatedPHID, relation);

ALTER TABLE phabricator_search.search_documentrelationship
  add key (relation, relatedPHID);
