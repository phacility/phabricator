ALTER TABLE {$NAMESPACE}_conpherence.conpherence_participant
  DROP KEY participantPHID,
  ADD KEY unreadCount (participantPHID, participationStatus),
  ADD KEY participationIndex (participantPHID, dateTouched, id);
