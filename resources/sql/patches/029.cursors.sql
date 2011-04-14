ALTER TABLE phabricator_timeline.timeline_event
  ADD dataID int unsigned;

ALTER TABLE phabricator_timeline.timeline_event
  ADD UNIQUE KEY (dataID);

UPDATE phabricator_timeline.timeline_event e,
       phabricator_timeline.timeline_eventdata d
  SET e.dataID = d.id
  WHERE d.eventID = e.id;

ALTER TABLE phabricator_timeline.timeline_eventdata
  DROP eventID;
