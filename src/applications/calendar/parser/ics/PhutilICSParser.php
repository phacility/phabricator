<?php

final class PhutilICSParser extends Phobject {

  private $stack;
  private $node;
  private $document;
  private $lines;
  private $cursor;

  private $warnings;

  const PARSE_MISSING_END = 'missing-end';
  const PARSE_INITIAL_UNFOLD = 'initial-unfold';
  const PARSE_UNEXPECTED_CHILD = 'unexpected-child';
  const PARSE_EXTRA_END = 'extra-end';
  const PARSE_MISMATCHED_SECTIONS = 'mismatched-sections';
  const PARSE_ROOT_PROPERTY = 'root-property';
  const PARSE_BAD_BASE64 = 'bad-base64';
  const PARSE_BAD_BOOLEAN = 'bad-boolean';
  const PARSE_UNEXPECTED_TEXT = 'unexpected-text';
  const PARSE_MALFORMED_DOUBLE_QUOTE = 'malformed-double-quote';
  const PARSE_MALFORMED_PARAMETER_NAME = 'malformed-parameter';
  const PARSE_MALFORMED_PROPERTY = 'malformed-property';
  const PARSE_MISSING_VALUE = 'missing-value';
  const PARSE_UNESCAPED_BACKSLASH = 'unescaped-backslash';
  const PARSE_MULTIPLE_PARAMETERS = 'multiple-parameters';
  const PARSE_EMPTY_DATETIME = 'empty-datetime';
  const PARSE_MANY_DATETIME = 'many-datetime';
  const PARSE_BAD_DATETIME = 'bad-datetime';
  const PARSE_EMPTY_DURATION = 'empty-duration';
  const PARSE_MANY_DURATION = 'many-duration';
  const PARSE_BAD_DURATION = 'bad-duration';

  const WARN_TZID_UTC = 'warn-tzid-utc';
  const WARN_TZID_GUESS = 'warn-tzid-guess';
  const WARN_TZID_IGNORED = 'warn-tzid-ignored';

  public function parseICSData($data) {
    $this->stack = array();
    $this->node = null;
    $this->cursor = null;
    $this->warnings = array();

    $lines = $this->unfoldICSLines($data);
    $this->lines = $lines;

    $root = $this->newICSNode('<ROOT>');
    $this->stack[] = $root;
    $this->node = $root;

    foreach ($lines as $key => $line) {
      $this->cursor = $key;
      $matches = null;
      if (preg_match('(^BEGIN:(.*)\z)', $line, $matches)) {
        $this->beginParsingNode($matches[1]);
      } else if (preg_match('(^END:(.*)\z)', $line, $matches)) {
        $this->endParsingNode($matches[1]);
      } else {
        if (count($this->stack) < 2) {
          $this->raiseParseFailure(
            self::PARSE_ROOT_PROPERTY,
            pht(
              'Found unexpected property at ICS document root.'));
        }
        $this->parseICSProperty($line);
      }
    }

    if (count($this->stack) > 1) {
      $this->raiseParseFailure(
        self::PARSE_MISSING_END,
        pht(
          'Expected all "BEGIN:" sections in ICS document to have '.
          'corresponding "END:" sections.'));
    }

    $this->node = null;
    $this->lines = null;
    $this->cursor = null;

    return $root;
  }

  private function getNode() {
    return $this->node;
  }

  private function unfoldICSLines($data) {
    $lines = phutil_split_lines($data, $retain_endings = false);
    $this->lines = $lines;

    // ICS files are wrapped at 75 characters, with overlong lines continued
    // on the following line with an initial space or tab. Unwrap all of the
    // lines in the file.

    // This unwrapping is specifically byte-oriented, not character oriented,
    // and RFC5545 anticipates that simple implementations may even split UTF8
    // characters in the middle.

    $last = null;
    foreach ($lines as $idx => $line) {
      $this->cursor = $idx;
      if (!preg_match('/^[ \t]/', $line)) {
        $last = $idx;
        continue;
      }

      if ($last === null) {
        $this->raiseParseFailure(
          self::PARSE_INITIAL_UNFOLD,
          pht(
            'First line of ICS file begins with a space or tab, but this '.
            'marks a line which should be unfolded.'));
      }

      $lines[$last] = $lines[$last].substr($line, 1);
      unset($lines[$idx]);
    }

    return $lines;
  }

  private function beginParsingNode($type) {
    $node = $this->getNode();
    $new_node = $this->newICSNode($type);

    if ($node instanceof PhutilCalendarContainerNode) {
      $node->appendChild($new_node);
    } else {
      $this->raiseParseFailure(
        self::PARSE_UNEXPECTED_CHILD,
        pht(
          'Found unexpected node "%s" inside node "%s".',
          $new_node->getAttribute('ics.type'),
          $node->getAttribute('ics.type')));
    }

    $this->stack[] = $new_node;
    $this->node = $new_node;

    return $this;
  }

  private function newICSNode($type) {
    switch ($type) {
      case '<ROOT>':
        $node = new PhutilCalendarRootNode();
        break;
      case 'VCALENDAR':
        $node = new PhutilCalendarDocumentNode();
        break;
      case 'VEVENT':
        $node = new PhutilCalendarEventNode();
        break;
      default:
        $node = new PhutilCalendarRawNode();
        break;
    }

    $node->setAttribute('ics.type', $type);

    return $node;
  }

  private function endParsingNode($type) {
    $node = $this->getNode();
    if ($node instanceof PhutilCalendarRootNode) {
      $this->raiseParseFailure(
        self::PARSE_EXTRA_END,
        pht(
          'Found unexpected "END" without a "BEGIN".'));
    }

    $old_type = $node->getAttribute('ics.type');
    if ($old_type != $type) {
      $this->raiseParseFailure(
        self::PARSE_MISMATCHED_SECTIONS,
        pht(
          'Found mismatched "BEGIN" ("%s") and "END" ("%s") sections.',
          $old_type,
          $type));
    }

    array_pop($this->stack);
    $this->node = last($this->stack);

    return $this;
  }

  private function parseICSProperty($line) {
    $matches = null;

    // Properties begin with an alphanumeric name with no escaping, followed
    // by either a ";" (to begin a list of parameters) or a ":" (to begin
    // the actual field body).

    $ok = preg_match('(^([A-Za-z0-9-]+)([;:])(.*)\z)', $line, $matches);
    if (!$ok) {
      $this->raiseParseFailure(
        self::PARSE_MALFORMED_PROPERTY,
        pht(
          'Found malformed property in ICS document.'));
    }

    $name = $matches[1];
    $body = $matches[3];
    $has_parameters = ($matches[2] == ';');

    $parameters = array();
    if ($has_parameters) {
      // Parameters are a sensible name, a literal "=", a pile of magic,
      // and then maybe a comma and another parameter.

      while (true) {
        // We're going to get the first couple of parts first.
        $ok = preg_match('(^([^=]+)=)', $body, $matches);
        if (!$ok) {
          $this->raiseParseFailure(
            self::PARSE_MALFORMED_PARAMETER_NAME,
            pht(
              'Found malformed property in ICS document: %s',
              $body));
        }

        $param_name = $matches[1];
        $body = substr($body, strlen($matches[0]));

        // Now we're going to match zero or more values.
        $param_values = array();
        while (true) {
          // The value can either be a double-quoted string or an unquoted
          // string, with some characters forbidden.
          if (strlen($body) && $body[0] == '"') {
            $is_quoted = true;
            $ok = preg_match(
              '(^"([^\x00-\x08\x10-\x19"]*)")',
              $body,
              $matches);
            if (!$ok) {
              $this->raiseParseFailure(
                self::PARSE_MALFORMED_DOUBLE_QUOTE,
                pht(
                  'Found malformed double-quoted string in ICS document '.
                  'parameter value.'));
            }
          } else {
            $is_quoted = false;

            // It's impossible for this not to match since it can match
            // nothing, and it's valid for it to match nothing.
            preg_match('(^([^\x00-\x08\x10-\x19";:,]*))', $body, $matches);
          }

          // NOTE: RFC5545 says "Property parameter values that are not in
          // quoted-strings are case-insensitive." -- that is, the quoted and
          // unquoted representations are not equivalent. Thus, preserve the
          // original formatting in case we ever need to respect this.

          $param_values[] = array(
            'value' => $this->unescapeParameterValue($matches[1]),
            'quoted' => $is_quoted,
          );

          $body = substr($body, strlen($matches[0]));
          if (!strlen($body)) {
            $this->raiseParseFailure(
              self::PARSE_MISSING_VALUE,
              pht(
                'Expected ":" after parameters in ICS document property.'));
          }

          // If we have a comma now, we're going to read another value. Strip
          // it off and keep going.
          if ($body[0] == ',') {
            $body = substr($body, 1);
            continue;
          }

          // If we have a semicolon, we're going to read another parameter.
          if ($body[0] == ';') {
            break;
          }

          // If we have a colon, this is the last value and also the last
          // property. Break, then handle the colon below.
          if ($body[0] == ':') {
            break;
          }

          $short_body = id(new PhutilUTF8StringTruncator())
            ->setMaximumGlyphs(32)
            ->truncateString($body);

          // We aren't expecting anything else.
          $this->raiseParseFailure(
            self::PARSE_UNEXPECTED_TEXT,
            pht(
              'Found unexpected text ("%s") after reading parameter value.',
              $short_body));
        }

        $parameters[] = array(
          'name' => $param_name,
          'values' => $param_values,
        );

        if ($body[0] == ';') {
          $body = substr($body, 1);
          continue;
        }

        if ($body[0] == ':') {
          $body = substr($body, 1);
          break;
        }
      }
    }

    $value = $this->unescapeFieldValue($name, $parameters, $body);

    $node = $this->getNode();


    $raw = $node->getAttribute('ics.properties', array());
    $raw[] = array(
      'name' => $name,
      'parameters' => $parameters,
      'value' => $value,
    );
    $node->setAttribute('ics.properties', $raw);

    switch ($node->getAttribute('ics.type')) {
      case 'VEVENT':
        $this->didParseEventProperty($node, $name, $parameters, $value);
        break;
    }
  }

  private function unescapeParameterValue($data) {
    // The parameter grammar is adjusted by RFC6868 to permit escaping with
    // carets. Remove that escaping.

    // This escaping is a bit weird because it's trying to be backwards
    // compatible and the original spec didn't think about this and didn't
    // provide much room to fix things.

    $out = '';
    $esc = false;
    foreach (phutil_utf8v($data) as $c) {
      if (!$esc) {
        if ($c != '^') {
          $out .= $c;
        } else {
          $esc = true;
        }
      } else {
        switch ($c) {
          case 'n':
            $out .= "\n";
            break;
          case '^':
            $out .= '^';
            break;
          case "'":
            // NOTE: This is "<caret> <single quote>" being decoded into a
            // double quote!
            $out .= '"';
            break;
          default:
            // NOTE: The caret is NOT an escape for any other characters.
            // This is a "MUST" requirement of RFC6868.
            $out .= '^'.$c;
            break;
          }
      }
    }

    // NOTE: Because caret on its own just means "caret" for backward
    // compatibility, we don't warn if we're still in escaped mode once we
    // reach the end of the string.

    return $out;
  }

  private function unescapeFieldValue($name, array $parameters, $data) {
    // NOTE: The encoding of the field value data is dependent on the field
    // name (which defines a default encoding) and the parameters (which may
    // include "VALUE", specifying a type of the data.

    $default_types = array(
      'CALSCALE' => 'TEXT',
      'METHOD' => 'TEXT',
      'PRODID' => 'TEXT',
      'VERSION' => 'TEXT',

      'ATTACH' => 'URI',
      'CATEGORIES' => 'TEXT',
      'CLASS' => 'TEXT',
      'COMMENT' => 'TEXT',
      'DESCRIPTION' => 'TEXT',

      // TODO: The spec appears to contradict itself: it says that the value
      // type is FLOAT, but it also says that this property value is actually
      // two semicolon-separated values, which is not what FLOAT is defined as.
      'GEO' => 'TEXT',

      'LOCATION' => 'TEXT',
      'PERCENT-COMPLETE' => 'INTEGER',
      'PRIORITY' => 'INTEGER',
      'RESOURCES' => 'TEXT',
      'STATUS' => 'TEXT',
      'SUMMARY' => 'TEXT',

      'COMPLETED' => 'DATE-TIME',
      'DTEND' => 'DATE-TIME',
      'DUE' => 'DATE-TIME',
      'DTSTART' => 'DATE-TIME',
      'DURATION' => 'DURATION',
      'FREEBUSY' => 'PERIOD',
      'TRANSP' => 'TEXT',

      'TZID' => 'TEXT',
      'TZNAME' => 'TEXT',
      'TZOFFSETFROM' => 'UTC-OFFSET',
      'TZOFFSETTO' => 'UTC-OFFSET',
      'TZURL' => 'URI',

      'ATTENDEE' => 'CAL-ADDRESS',
      'CONTACT' => 'TEXT',
      'ORGANIZER' => 'CAL-ADDRESS',
      'RECURRENCE-ID' => 'DATE-TIME',
      'RELATED-TO' => 'TEXT',
      'URL' => 'URI',
      'UID' => 'TEXT',
      'EXDATE' => 'DATE-TIME',
      'RDATE' => 'DATE-TIME',
      'RRULE' => 'RECUR',

      'ACTION' => 'TEXT',
      'REPEAT' => 'INTEGER',
      'TRIGGER' => 'DURATION',

      'CREATED' => 'DATE-TIME',
      'DTSTAMP' => 'DATE-TIME',
      'LAST-MODIFIED' => 'DATE-TIME',
      'SEQUENCE' => 'INTEGER',

      'REQUEST-STATUS' => 'TEXT',
    );

    $value_type = idx($default_types, $name, 'TEXT');

    foreach ($parameters as $parameter) {
      if ($parameter['name'] == 'VALUE') {
        $value_type = idx(head($parameter['values']), 'value');
      }
    }

    switch ($value_type) {
      case 'BINARY':
        $result = base64_decode($data, true);
        if ($result === false) {
          $this->raiseParseFailure(
            self::PARSE_BAD_BASE64,
            pht(
              'Unable to decode base64 data: %s',
              $data));
        }
        break;
      case 'BOOLEAN':
        $map = array(
          'true' => true,
          'false' => false,
        );
        $result = phutil_utf8_strtolower($data);
        if (!isset($map[$result])) {
          $this->raiseParseFailure(
            self::PARSE_BAD_BOOLEAN,
            pht(
              'Unexpected BOOLEAN value "%s".',
              $data));
        }
        $result = $map[$result];
        break;
      case 'CAL-ADDRESS':
        $result = $data;
        break;
      case 'DATE':
        // This is a comma-separated list of "YYYYMMDD" values.
        $result = explode(',', $data);
        break;
      case 'DATE-TIME':
        if (!strlen($data)) {
          $result = array();
        } else {
          $result = explode(',', $data);
        }
        break;
      case 'DURATION':
        if (!strlen($data)) {
          $result = array();
        } else {
          $result = explode(',', $data);
        }
        break;
      case 'FLOAT':
        $result = explode(',', $data);
        foreach ($result as $k => $v) {
          $result[$k] = (float)$v;
        }
        break;
      case 'INTEGER':
        $result = explode(',', $data);
        foreach ($result as $k => $v) {
          $result[$k] = (int)$v;
        }
        break;
      case 'PERIOD':
        $result = explode(',', $data);
        break;
      case 'RECUR':
        $result = $data;
        break;
      case 'TEXT':
        $result = $this->unescapeTextValue($data);
        break;
      case 'TIME':
        $result = explode(',', $data);
        break;
      case 'URI':
        $result = $data;
        break;
      case 'UTC-OFFSET':
        $result = $data;
        break;
      default:
        // RFC5545 says we MUST preserve the data for any types we don't
        // recognize.
        $result = $data;
        break;
    }

    return array(
      'type' => $value_type,
      'value' => $result,
      'raw' => $data,
    );
  }

  private function unescapeTextValue($data) {
    $result = array();

    $buf = '';
    $esc = false;
    foreach (phutil_utf8v($data) as $c) {
      if (!$esc) {
        if ($c == '\\') {
          $esc = true;
        } else if ($c == ',') {
          $result[] = $buf;
          $buf = '';
        } else {
          $buf .= $c;
        }
      } else {
        switch ($c) {
          case 'n':
          case 'N':
            $buf .= "\n";
            break;
          default:
            $buf .= $c;
            break;
        }
        $esc = false;
      }
    }

    if ($esc) {
      $this->raiseParseFailure(
        self::PARSE_UNESCAPED_BACKSLASH,
        pht(
          'ICS document contains TEXT value ending with unescaped '.
          'backslash.'));
    }

    $result[] = $buf;

    return $result;
  }

  private function raiseParseFailure($code, $message) {
    if ($this->lines && isset($this->lines[$this->cursor])) {
      $message = pht(
        "ICS Parse Error near line %s:\n\n>>> %s\n\n%s",
        $this->cursor + 1,
        $this->lines[$this->cursor],
        $message);
    } else {
      $message = pht(
        'ICS Parse Error: %s',
        $message);
    }

    throw id(new PhutilICSParserException($message))
      ->setParserFailureCode($code);
  }

  private function raiseWarning($code, $message) {
    $this->warnings[] = array(
      'code' => $code,
      'line' => $this->cursor,
      'text' => $this->lines[$this->cursor],
      'message' => $message,
    );

    return $this;
  }

  public function getWarnings() {
    return $this->warnings;
  }

  private function didParseEventProperty(
    PhutilCalendarEventNode $node,
    $name,
    array $parameters,
    array $value) {

    switch ($name) {
      case 'UID':
        $text = $this->newTextFromProperty($parameters, $value);
        $node->setUID($text);
        break;
      case 'CREATED':
        $datetime = $this->newDateTimeFromProperty($parameters, $value);
        $node->setCreatedDateTime($datetime);
        break;
      case 'DTSTAMP':
        $datetime = $this->newDateTimeFromProperty($parameters, $value);
        $node->setModifiedDateTime($datetime);
        break;
      case 'SUMMARY':
        $text = $this->newTextFromProperty($parameters, $value);
        $node->setName($text);
        break;
      case 'DESCRIPTION':
        $text = $this->newTextFromProperty($parameters, $value);
        $node->setDescription($text);
        break;
      case 'DTSTART':
        $datetime = $this->newDateTimeFromProperty($parameters, $value);
        $node->setStartDateTime($datetime);
        break;
      case 'DTEND':
        $datetime = $this->newDateTimeFromProperty($parameters, $value);
        $node->setEndDateTime($datetime);
        break;
      case 'DURATION':
        $duration = $this->newDurationFromProperty($parameters, $value);
        $node->setDuration($duration);
        break;
      case 'RRULE':
        $rrule = $this->newRecurrenceRuleFromProperty($parameters, $value);
        $node->setRecurrenceRule($rrule);
        break;
      case 'RECURRENCE-ID':
        $text = $this->newTextFromProperty($parameters, $value);
        $node->setRecurrenceID($text);
        break;
      case 'ATTENDEE':
        $attendee = $this->newAttendeeFromProperty($parameters, $value);
        $node->addAttendee($attendee);
        break;
    }

  }

  private function newTextFromProperty(array $parameters, array $value) {
    $value = $value['value'];
    return implode("\n\n", $value);
  }

  private function newAttendeeFromProperty(array $parameters, array $value) {
    $uri = $value['value'];

    switch (idx($parameters, 'PARTSTAT')) {
      case 'ACCEPTED':
        $status = PhutilCalendarUserNode::STATUS_ACCEPTED;
        break;
      case 'DECLINED':
        $status = PhutilCalendarUserNode::STATUS_DECLINED;
        break;
      case 'NEEDS-ACTION':
      default:
        $status = PhutilCalendarUserNode::STATUS_INVITED;
        break;
    }

    $name = $this->getScalarParameterValue($parameters, 'CN');

    return id(new PhutilCalendarUserNode())
      ->setURI($uri)
      ->setName($name)
      ->setStatus($status);
  }

  private function newDateTimeFromProperty(array $parameters, array $value) {
    $value = $value['value'];

    if (!$value) {
      $this->raiseParseFailure(
        self::PARSE_EMPTY_DATETIME,
        pht(
          'Expected DATE-TIME to have exactly one value, found none.'));

    }

    if (count($value) > 1) {
      $this->raiseParseFailure(
        self::PARSE_MANY_DATETIME,
        pht(
          'Expected DATE-TIME to have exactly one value, found more than '.
          'one.'));
    }

    $value = head($value);
    $tzid = $this->getScalarParameterValue($parameters, 'TZID');

    if (preg_match('/Z\z/', $value)) {
      if ($tzid) {
        $this->raiseWarning(
          self::WARN_TZID_UTC,
          pht(
            'DATE-TIME "%s" uses "Z" to specify UTC, but also has a TZID '.
            'parameter with value "%s". This violates RFC5545. The TZID '.
            'will be ignored, and the value will be interpreted as UTC.',
            $value,
            $tzid));
      }
      $tzid = 'UTC';
    } else if ($tzid !== null) {
      $tzid = $this->guessTimezone($tzid);
    }

    try {
      $datetime = PhutilCalendarAbsoluteDateTime::newFromISO8601(
        $value,
        $tzid);
    } catch (Exception $ex) {
      $this->raiseParseFailure(
        self::PARSE_BAD_DATETIME,
        pht(
          'Error parsing DATE-TIME: %s',
          $ex->getMessage()));
    }

    return $datetime;
  }

  private function newDurationFromProperty(array $parameters, array $value) {
    $value = $value['value'];

    if (!$value) {
      $this->raiseParseFailure(
        self::PARSE_EMPTY_DURATION,
        pht(
          'Expected DURATION to have exactly one value, found none.'));

    }

    if (count($value) > 1) {
      $this->raiseParseFailure(
        self::PARSE_MANY_DURATION,
        pht(
          'Expected DURATION to have exactly one value, found more than '.
          'one.'));
    }

    $value = head($value);

    try {
      $duration = PhutilCalendarDuration::newFromISO8601($value);
    } catch (Exception $ex) {
      $this->raiseParseFailure(
        self::PARSE_BAD_DURATION,
        pht(
          'Invalid DURATION: %s',
          $ex->getMessage()));
    }

    return $duration;
  }

  private function newRecurrenceRuleFromProperty(array $parameters, $value) {
    return PhutilCalendarRecurrenceRule::newFromRRULE($value['value']);
  }

  private function getScalarParameterValue(
    array $parameters,
    $name,
    $default = null) {

    $match = null;
    foreach ($parameters as $parameter) {
      if ($parameter['name'] == $name) {
        $match = $parameter;
      }
    }

    if ($match === null) {
      return $default;
    }

    $value = $match['values'];
    if (!$value) {
      // Parameter is specified, but with no value, like "KEY=". Just return
      // the default, as though the parameter was not specified.
      return $default;
    }

    if (count($value) > 1) {
      $this->raiseParseFailure(
        self::PARSE_MULTIPLE_PARAMETERS,
        pht(
          'Expected parameter "%s" to have at most one value, but found '.
          'more than one.',
          $name));
    }

    return idx(head($value), 'value');
  }

  private function guessTimezone($tzid) {
    $map = DateTimeZone::listIdentifiers();
    $map = array_fuse($map);
    if (isset($map[$tzid])) {
      // This is a real timezone we recognize, so just use it as provided.
      return $tzid;
    }

    // These are alternate names for timezones.
    static $aliases;

    if ($aliases === null) {
      $aliases = array(
        'Etc/GMT' => 'UTC',
      );

      // Load the map of Windows timezones.
      $root_path = dirname(phutil_get_library_root('phabricator'));
      $windows_path = $root_path.'/resources/timezones/windows-timezones.json';
      $windows_data = Filesystem::readFile($windows_path);
      $windows_zones = phutil_json_decode($windows_data);

      $aliases = $aliases + $windows_zones;
    }

    if (isset($aliases[$tzid])) {
      return $aliases[$tzid];
    }

    // Look for something that looks like "UTC+3" or "GMT -05.00". If we find
    // anything, pick a timezone with that offset.
    $offset_pattern =
      '/'.
      '(?:UTC|GMT)'.
      '\s*'.
      '(?P<sign>[+-])'.
      '\s*'.
      '(?P<h>\d+)'.
      '(?:'.
        '[:.](?P<m>\d+)'.
      ')?'.
      '/i';

    $matches = null;
    if (preg_match($offset_pattern, $tzid, $matches)) {
      $hours = (int)$matches['h'];
      $minutes = (int)idx($matches, 'm');
      $offset = ($hours * 60 * 60) + ($minutes * 60);

      if (idx($matches, 'sign') == '-') {
        $offset = -$offset;
      }

      // NOTE: We could possibly do better than this, by using the event start
      // time to guess a timezone. However, that won't work for recurring
      // events and would require us to do this work after finishing initial
      // parsing. Since these unusual offset-based timezones appear to be rare,
      // the benefit may not be worth the complexity.
      $now = new DateTime('@'.time());

      foreach ($map as $identifier) {
        $zone = new DateTimeZone($identifier);
        if ($zone->getOffset($now) == $offset) {
          $this->raiseWarning(
            self::WARN_TZID_GUESS,
            pht(
              'TZID "%s" is unknown, guessing "%s" based on pattern "%s".',
              $tzid,
              $identifier,
              $matches[0]));
          return $identifier;
        }
      }
    }

    $this->raiseWarning(
      self::WARN_TZID_IGNORED,
      pht(
        'TZID "%s" is unknown, using UTC instead.',
        $tzid));

    return 'UTC';
  }

}
