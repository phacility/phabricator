UPDATE {$NAMESPACE}_calendar.calendar_eventinvitee
  SET availability = 'default'
  WHERE availability = '';
