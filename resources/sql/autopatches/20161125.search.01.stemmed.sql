ALTER TABLE {$NAMESPACE}_search.search_documentfield
  ADD stemmedCorpus LONGTEXT COLLATE {$COLLATE_FULLTEXT};
