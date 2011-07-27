ALTER TABLE phabricator_herald.herald_transcript
  ADD garbageCollected BOOL NOT NULL DEFAULT 0;

UPDATE phabricator_herald.herald_transcript
  SET garbageCollected = 1
  WHERE objectTranscript = "";

ALTER TABLE phabricator_herald.herald_transcript
  ADD KEY (garbageCollected, time);
