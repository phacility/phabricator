ALTER TABLE search_documentrelationship add key (relatedPHID, relation);
ALTER TABLE search_documentrelationship add key (relation, relatedPHID);
