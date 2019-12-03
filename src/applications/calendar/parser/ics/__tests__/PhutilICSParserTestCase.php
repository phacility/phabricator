<?php

final class PhutilICSParserTestCase extends PhutilTestCase {

  public function testICSParser() {
    $event = $this->parseICSSingleEvent('simple.ics');

    $this->assertEqual(
      array(
        array(
          'name' => 'CREATED',
          'parameters' => array(),
          'value' => array(
            'type' => 'DATE-TIME',
            'value' => array(
              '20160908T172702Z',
            ),
            'raw' => '20160908T172702Z',
          ),
        ),
        array(
          'name' => 'UID',
          'parameters' => array(),
          'value' => array(
            'type' => 'TEXT',
            'value' => array(
              '1CEB57AF-0C9C-402D-B3BD-D75BD4843F68',
            ),
            'raw' => '1CEB57AF-0C9C-402D-B3BD-D75BD4843F68',
          ),
        ),
        array(
          'name' => 'DTSTART',
          'parameters' => array(
            array(
              'name' => 'TZID',
              'values' => array(
                array(
                  'value' => 'America/Los_Angeles',
                  'quoted' => false,
                ),
              ),
            ),
          ),
          'value' => array(
            'type' => 'DATE-TIME',
            'value' => array(
              '20160915T090000',
            ),
            'raw' => '20160915T090000',
          ),
        ),
        array(
          'name' => 'DTEND',
          'parameters' => array(
            array(
              'name' => 'TZID',
              'values' => array(
                array(
                  'value' => 'America/Los_Angeles',
                  'quoted' => false,
                ),
              ),
            ),
          ),
          'value' => array(
            'type' => 'DATE-TIME',
            'value' => array(
              '20160915T100000',
            ),
            'raw' => '20160915T100000',
          ),
        ),
        array(
          'name' => 'SUMMARY',
          'parameters' => array(),
          'value' => array(
            'type' => 'TEXT',
            'value' => array(
              'Simple Event',
            ),
            'raw' => 'Simple Event',
          ),
        ),
        array(
          'name' => 'DESCRIPTION',
          'parameters' => array(),
          'value' => array(
            'type' => 'TEXT',
            'value' => array(
              'This is a simple event.',
            ),
            'raw' => 'This is a simple event.',
          ),
        ),
      ),
      $event->getAttribute('ics.properties'));

    $this->assertEqual(
      'Simple Event',
      $event->getName());

    $this->assertEqual(
      'This is a simple event.',
      $event->getDescription());

    $this->assertEqual(
      1473955200,
      $event->getStartDateTime()->getEpoch());

    $this->assertEqual(
      1473955200 + phutil_units('1 hour in seconds'),
      $event->getEndDateTime()->getEpoch());
  }

  public function testICSOddTimezone() {
    $event = $this->parseICSSingleEvent('zimbra-timezone.ics');

    $start = $event->getStartDateTime();

    $this->assertEqual(
      '20170303T140000Z',
      $start->getISO8601());
  }

  public function testICSFloatingTime() {
    // This tests "floating" event times, which have no absolute time and are
    // supposed to be interpreted using the viewer's timezone. It also uses
    // a duration, and the duration needs to float along with the viewer
    // timezone.

    $event = $this->parseICSSingleEvent('floating.ics');

    $start = $event->getStartDateTime();

    $caught = null;
    try {
      $start->getEpoch();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof Exception),
      pht('Expected exception for floating time with no viewer timezone.'));

    $newyears_utc = strtotime('2015-01-01 00:00:00 UTC');
    $this->assertEqual(1420070400, $newyears_utc);

    $start->setViewerTimezone('UTC');
    $this->assertEqual(
      $newyears_utc,
      $start->getEpoch());

    $start->setViewerTimezone('America/Los_Angeles');
    $this->assertEqual(
      $newyears_utc + phutil_units('8 hours in seconds'),
      $start->getEpoch());

    $start->setViewerTimezone('America/New_York');
    $this->assertEqual(
      $newyears_utc + phutil_units('5 hours in seconds'),
      $start->getEpoch());

    $end = $event->getEndDateTime();
    $end->setViewerTimezone('UTC');
    $this->assertEqual(
      $newyears_utc + phutil_units('24 hours in seconds'),
      $end->getEpoch());

    $end->setViewerTimezone('America/Los_Angeles');
    $this->assertEqual(
      $newyears_utc + phutil_units('32 hours in seconds'),
      $end->getEpoch());

    $end->setViewerTimezone('America/New_York');
    $this->assertEqual(
      $newyears_utc + phutil_units('29 hours in seconds'),
      $end->getEpoch());
  }

  public function testICSVALARM() {
    $event = $this->parseICSSingleEvent('valarm.ics');

    // For now, we parse but ignore VALARM sections. This test just makes
    // sure they survive parsing.

    $start_epoch = strtotime('2016-10-19 22:00:00 UTC');
    $this->assertEqual(1476914400, $start_epoch);

    $this->assertEqual(
      $start_epoch,
      $event->getStartDateTime()->getEpoch());
  }

  public function testICSDuration() {
    $event = $this->parseICSSingleEvent('duration.ics');

    // Raw value is "20160719T095722Z".
    $start_epoch = strtotime('2016-07-19 09:57:22 UTC');
    $this->assertEqual(1468922242, $start_epoch);

    // Raw value is "P1DT17H4M23S".
    $duration =
      phutil_units('1 day in seconds') +
      phutil_units('17 hours in seconds') +
      phutil_units('4 minutes in seconds') +
      phutil_units('23 seconds in seconds');

    $this->assertEqual(
      $start_epoch,
      $event->getStartDateTime()->getEpoch());

    $this->assertEqual(
      $start_epoch + $duration,
      $event->getEndDateTime()->getEpoch());
  }

  public function testICSWeeklyEvent() {
    $event = $this->parseICSSingleEvent('weekly.ics');

    $start = $event->getStartDateTime();
    $start->setViewerTimezone('UTC');

    $rrule = $event->getRecurrenceRule()
      ->setStartDateTime($start);

    $rset = id(new PhutilCalendarRecurrenceSet())
      ->addSource($rrule);

    $result = $rset->getEventsBetween(null, null, 3);

    $expect = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20150811'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20150818'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20150825'),
    );

    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Weekly recurring event.'));
  }

  public function testICSParserErrors() {
    $map = array(
      'err-missing-end.ics' => PhutilICSParser::PARSE_MISSING_END,
      'err-bad-base64.ics' => PhutilICSParser::PARSE_BAD_BASE64,
      'err-bad-boolean.ics' => PhutilICSParser::PARSE_BAD_BOOLEAN,
      'err-extra-end.ics' => PhutilICSParser::PARSE_EXTRA_END,
      'err-initial-unfold.ics' => PhutilICSParser::PARSE_INITIAL_UNFOLD,
      'err-malformed-double-quote.ics' =>
        PhutilICSParser::PARSE_MALFORMED_DOUBLE_QUOTE,
      'err-malformed-parameter.ics' =>
        PhutilICSParser::PARSE_MALFORMED_PARAMETER_NAME,
      'err-malformed-property.ics' =>
        PhutilICSParser::PARSE_MALFORMED_PROPERTY,
      'err-missing-value.ics' => PhutilICSParser::PARSE_MISSING_VALUE,
      'err-mixmatched-sections.ics' =>
        PhutilICSParser::PARSE_MISMATCHED_SECTIONS,
      'err-root-property.ics' => PhutilICSParser::PARSE_ROOT_PROPERTY,
      'err-unescaped-backslash.ics' =>
        PhutilICSParser::PARSE_UNESCAPED_BACKSLASH,
      'err-unexpected-text.ics' => PhutilICSParser::PARSE_UNEXPECTED_TEXT,
      'err-multiple-parameters.ics' =>
        PhutilICSParser::PARSE_MULTIPLE_PARAMETERS,
      'err-empty-datetime.ics' =>
        PhutilICSParser::PARSE_EMPTY_DATETIME,
      'err-many-datetime.ics' =>
        PhutilICSParser::PARSE_MANY_DATETIME,
      'err-bad-datetime.ics' =>
        PhutilICSParser::PARSE_BAD_DATETIME,
      'err-empty-duration.ics' =>
        PhutilICSParser::PARSE_EMPTY_DURATION,
      'err-many-duration.ics' =>
        PhutilICSParser::PARSE_MANY_DURATION,
      'err-bad-duration.ics' =>
        PhutilICSParser::PARSE_BAD_DURATION,

      'simple.ics' => null,
      'good-boolean.ics' => null,
      'multiple-vcalendars.ics' => null,
    );

    foreach ($map as $test_file => $expect) {
      $caught = null;
      try {
        $this->parseICSDocument($test_file);
      } catch (PhutilICSParserException $ex) {
        $caught = $ex;
      }

      if ($expect === null) {
        $this->assertTrue(
          ($caught === null),
          pht(
            'Expected no exception parsing "%s", got: %s',
            $test_file,
            (string)$ex));
      } else {
        if ($caught) {
          $code = $ex->getParserFailureCode();
          $explain = pht(
            'Expected one exception parsing "%s", got a different '.
            'one: %s',
            $test_file,
            (string)$ex);
        } else {
          $code = null;
          $explain = pht(
            'Expected exception parsing "%s", got none.',
            $test_file);
        }

        $this->assertEqual($expect, $code, $explain);
      }
    }
  }

  private function parseICSSingleEvent($name) {
    $root = $this->parseICSDocument($name);

    $documents = $root->getDocuments();
    $this->assertEqual(1, count($documents));
    $document = head($documents);

    $events = $document->getEvents();
    $this->assertEqual(1, count($events));

    return head($events);
  }

  private function parseICSDocument($name) {
    $path = dirname(__FILE__).'/data/'.$name;
    $data = Filesystem::readFile($path);
    return id(new PhutilICSParser())
      ->parseICSData($data);
  }


}
