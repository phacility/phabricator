<?php

final class PhutilICSWriter extends Phobject {

  public function writeICSDocument(PhutilCalendarRootNode $node) {
    $out = array();

    foreach ($node->getChildren() as $child) {
      $out[] = $this->writeNode($child);
    }

    return implode('', $out);
  }

  private function writeNode(PhutilCalendarNode $node) {
    if (!$this->getICSNodeType($node)) {
      return null;
    }

    $out = array();

    $out[] = $this->writeBeginNode($node);
    $out[] = $this->writeNodeProperties($node);

    if ($node instanceof PhutilCalendarContainerNode) {
      foreach ($node->getChildren() as $child) {
        $out[] = $this->writeNode($child);
      }
    }

    $out[] = $this->writeEndNode($node);

    return implode('', $out);
  }

  private function writeBeginNode(PhutilCalendarNode $node) {
    $type = $this->getICSNodeType($node);
    return $this->wrapICSLine("BEGIN:{$type}");
  }

  private function writeEndNode(PhutilCalendarNode $node) {
    $type = $this->getICSNodeType($node);
    return $this->wrapICSLine("END:{$type}");
  }

  private function writeNodeProperties(PhutilCalendarNode $node) {
    $properties = $this->getNodeProperties($node);

    $out = array();
    foreach ($properties as $property) {
      $propname = $property['name'];
      $propvalue = $property['value'];

      $propline = array();
      $propline[] = $propname;

      foreach ($property['parameters'] as $parameter) {
        $paramname = $parameter['name'];
        $paramvalue = $parameter['value'];
        $propline[] = ";{$paramname}={$paramvalue}";
      }

      $propline[] = ":{$propvalue}";
      $propline = implode('', $propline);

      $out[] = $this->wrapICSLine($propline);
    }

    return implode('', $out);
  }

  private function getICSNodeType(PhutilCalendarNode $node) {
    switch ($node->getNodeType()) {
      case PhutilCalendarDocumentNode::NODETYPE:
        return 'VCALENDAR';
      case PhutilCalendarEventNode::NODETYPE:
        return 'VEVENT';
      default:
        return null;
    }
  }

  private function wrapICSLine($line) {
    $out = array();
    $buf = '';

    // NOTE: The line may contain sequences of combining characters which are
    // more than 80 bytes in length. If it does, we'll split them in the
    // middle of the sequence. This is okay and generally anticipated by
    // RFC5545, which even allows implementations to split multibyte
    // characters. The sequence will be stitched back together properly by
    // whatever is parsing things.

    foreach (phutil_utf8v($line) as $character) {
      // If adding this character would bring the line over 75 bytes, start
      // a new line.
      if (strlen($buf) + strlen($character) > 75) {
        $out[] = $buf."\r\n";
        $buf = ' ';
      }

      $buf .= $character;
    }

    $out[] = $buf."\r\n";

    return implode('', $out);
  }

  private function getNodeProperties(PhutilCalendarNode $node) {
    switch ($node->getNodeType()) {
      case PhutilCalendarDocumentNode::NODETYPE:
        return $this->getDocumentNodeProperties($node);
      case PhutilCalendarEventNode::NODETYPE:
        return $this->getEventNodeProperties($node);
      default:
        return array();
    }
  }

  private function getDocumentNodeProperties(
    PhutilCalendarDocumentNode $event) {
    $properties = array();

    $properties[] = $this->newTextProperty(
      'VERSION',
      '2.0');

    $properties[] = $this->newTextProperty(
      'PRODID',
      self::getICSPRODID());

    return $properties;
  }

  public static function getICSPRODID() {
    return '-//Phacility//Phabricator//EN';
  }

  private function getEventNodeProperties(PhutilCalendarEventNode $event) {
    $properties = array();

    $uid = $event->getUID();
    if (!strlen($uid)) {
      throw new Exception(
        pht(
          'Unable to write ICS document: event has no UID, but each event '.
          'MUST have a UID.'));
    }
    $properties[] = $this->newTextProperty(
      'UID',
      $uid);

    $created = $event->getCreatedDateTime();
    if ($created) {
      $properties[] = $this->newDateTimeProperty(
        'CREATED',
        $event->getCreatedDateTime());
    }

    $dtstamp = $event->getModifiedDateTime();
    if (!$dtstamp) {
      throw new Exception(
        pht(
          'Unable to write ICS document: event has no modified time, but '.
          'each event MUST have a modified time.'));
    }
    $properties[] = $this->newDateTimeProperty(
      'DTSTAMP',
      $dtstamp);

    $dtstart = $event->getStartDateTime();
    if ($dtstart) {
      $properties[] = $this->newDateTimeProperty(
        'DTSTART',
        $dtstart);
    }

    $dtend = $event->getEndDateTime();
    if ($dtend) {
      $properties[] = $this->newDateTimeProperty(
        'DTEND',
        $event->getEndDateTime());
    }

    $name = $event->getName();
    if (phutil_nonempty_string($name)) {
      $properties[] = $this->newTextProperty(
        'SUMMARY',
        $name);
    }

    $description = $event->getDescription();
    if (phutil_nonempty_string($description)) {
      $properties[] = $this->newTextProperty(
        'DESCRIPTION',
        $description);
    }

    $organizer = $event->getOrganizer();
    if ($organizer) {
      $properties[] = $this->newUserProperty(
        'ORGANIZER',
        $organizer);
    }

    $attendees = $event->getAttendees();
    if ($attendees) {
      foreach ($attendees as $attendee) {
        $properties[] = $this->newUserProperty(
          'ATTENDEE',
          $attendee);
      }
    }

    $rrule = $event->getRecurrenceRule();
    if ($rrule) {
      $properties[] = $this->newRRULEProperty(
        'RRULE',
        $rrule);
    }

    $recurrence_id = $event->getRecurrenceID();
    if ($recurrence_id) {
      $properties[] = $this->newTextProperty(
        'RECURRENCE-ID',
        $recurrence_id);
    }

    $exdates = $event->getRecurrenceExceptions();
    if ($exdates) {
      $properties[] = $this->newDateTimesProperty(
        'EXDATE',
        $exdates);
    }

    $rdates = $event->getRecurrenceDates();
    if ($rdates) {
      $properties[] = $this->newDateTimesProperty(
        'RDATE',
        $rdates);
    }

    return $properties;
  }

  private function newTextProperty(
    $name,
    $value,
    array $parameters = array()) {

    $map = array(
      '\\' => '\\\\',
      ',' => '\\,',
      "\n" => '\\n',
    );

    $value = (array)$value;
    foreach ($value as $k => $v) {
      $v = str_replace(array_keys($map), array_values($map), $v);
      $value[$k] = $v;
    }

    $value = implode(',', $value);

    return $this->newProperty($name, $value, $parameters);
  }

  private function newDateTimeProperty(
    $name,
    PhutilCalendarDateTime $value,
    array $parameters = array()) {

    return $this->newDateTimesProperty($name, array($value), $parameters);
  }

  private function newDateTimesProperty(
    $name,
    array $values,
    array $parameters = array()) {
    assert_instances_of($values, 'PhutilCalendarDateTime');

    if (head($values)->getIsAllDay()) {
      $parameters[] = array(
        'name' => 'VALUE',
        'values' => array(
          'DATE',
        ),
      );
    }

    $datetimes = array();
    foreach ($values as $value) {
      $datetimes[] = $value->getISO8601();
    }
    $datetimes = implode(';', $datetimes);

    return $this->newProperty($name, $datetimes, $parameters);
  }

  private function newUserProperty(
    $name,
    PhutilCalendarUserNode $value,
    array $parameters = array()) {

    $parameters[] = array(
      'name' => 'CN',
      'values' => array(
        $value->getName(),
      ),
    );

    $partstat = null;
    switch ($value->getStatus()) {
      case PhutilCalendarUserNode::STATUS_INVITED:
        $partstat = 'NEEDS-ACTION';
        break;
      case PhutilCalendarUserNode::STATUS_ACCEPTED:
        $partstat = 'ACCEPTED';
        break;
      case PhutilCalendarUserNode::STATUS_DECLINED:
        $partstat = 'DECLINED';
        break;
    }

    if ($partstat !== null) {
      $parameters[] = array(
        'name' => 'PARTSTAT',
        'values' => array(
          $partstat,
        ),
      );
    }

    // TODO: We could reasonably fill in "ROLE" and "RSVP" here too, but it
    // isn't clear if these are important to external programs or not.

    return $this->newProperty($name, $value->getURI(), $parameters);
  }

  private function newRRULEProperty(
    $name,
    PhutilCalendarRecurrenceRule $rule,
    array $parameters = array()) {

    $value = $rule->toRRULE();
    return $this->newProperty($name, $value, $parameters);
  }

  private function newProperty(
    $name,
    $value,
    array $parameters = array()) {

    $map = array(
      '^' => '^^',
      "\n" => '^n',
      '"' => "^'",
    );

    $writable_params = array();
    foreach ($parameters as $k => $parameter) {
      $value_list = array();
      foreach ($parameter['values'] as $v) {
        $v = str_replace(array_keys($map), array_values($map), $v);

        // If the parameter value isn't a very simple one, quote it.

        // RFC5545 says that we MUST quote it if it has a colon, a semicolon,
        // or a comma, and that we MUST quote it if it's a URI.
        if (!preg_match('/^[A-Za-z0-9-]*\z/', $v)) {
          $v = '"'.$v.'"';
        }

        $value_list[] = $v;
      }

      $writable_params[] = array(
        'name' => $parameter['name'],
        'value' => implode(',', $value_list),
      );
    }

    return array(
      'name' => $name,
      'value' => $value,
      'parameters' => $writable_params,
    );
  }

}
