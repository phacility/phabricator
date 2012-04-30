ALTER TABLE {$NAMESPACE}_timeline.timeline_event
  ADD dataID int unsigned;

ALTER TABLE {$NAMESPACE}_timeline.timeline_event
  ADD UNIQUE KEY (dataID);

UPDATE {$NAMESPACE}_timeline.timeline_event e,
       {$NAMESPACE}_timeline.timeline_eventdata d
  SET e.dataID = d.id
  WHERE d.eventID = e.id;

ALTER TABLE {$NAMESPACE}_timeline.timeline_eventdata
  DROP eventID;
