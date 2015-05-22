<?php

/**
 * Parses commit messages (containing relatively freeform text with textual
 * field labels) into a dictionary of fields.
 *
 *   $parser = id(new DifferentialCommitMessageParser())
 *     ->setLabelMap($label_map)
 *     ->setTitleKey($key_title)
 *     ->setSummaryKey($key_summary);
 *
 *   $fields = $parser->parseCorpus($corpus);
 *   $errors = $parser->getErrors();
 *
 * This is used by Differential to parse messages entered from the command line.
 *
 * @task config   Configuring the Parser
 * @task parse    Parsing Messages
 * @task support  Support Methods
 * @task internal Internals
 */
final class DifferentialCommitMessageParser {

  private $labelMap;
  private $titleKey;
  private $summaryKey;
  private $errors;


/* -(  Configuring the Parser  )--------------------------------------------- */


  /**
   * @task config
   */
  public function setLabelMap(array $label_map) {
    $this->labelMap = $label_map;
    return $this;
  }


  /**
   * @task config
   */
  public function setTitleKey($title_key) {
    $this->titleKey = $title_key;
    return $this;
  }


  /**
   * @task config
   */
  public function setSummaryKey($summary_key) {
    $this->summaryKey = $summary_key;
    return $this;
  }


/* -(  Parsing Messages  )--------------------------------------------------- */


  /**
   * @task parse
   */
  public function parseCorpus($corpus) {
    $this->errors = array();

    $label_map = $this->labelMap;
    $key_title = $this->titleKey;
    $key_summary = $this->summaryKey;

    if (!$key_title || !$key_summary || ($label_map === null)) {
      throw new Exception(
        pht(
          'Expected %s, %s and %s to be set before parsing a corpus.',
          'labelMap',
          'summaryKey',
          'titleKey'));
    }

    $label_regexp = $this->buildLabelRegexp($label_map);

    // NOTE: We're special casing things here to make the "Title:" label
    // optional in the message.
    $field = $key_title;

    $seen = array();
    $lines = explode("\n", trim($corpus));
    $field_map = array();
    foreach ($lines as $key => $line) {
      $match = null;
      if (preg_match($label_regexp, $line, $match)) {
        $lines[$key] = trim($match['text']);
        $field = $label_map[self::normalizeFieldLabel($match['field'])];
        if (!empty($seen[$field])) {
          $this->errors[] = pht(
            'Field "%s" occurs twice in commit message!',
            $field);
        }
        $seen[$field] = true;
      }
      $field_map[$key] = $field;
    }

    $fields = array();
    foreach ($lines as $key => $line) {
      $fields[$field_map[$key]][] = $line;
    }

    // This is a piece of special-cased magic which allows you to omit the
    // field labels for "title" and "summary". If the user enters a large block
    // of text at the beginning of the commit message with an empty line in it,
    // treat everything before the blank line as "title" and everything after
    // as "summary".
    if (isset($fields[$key_title]) && empty($fields[$key_summary])) {
      $lines = $fields[$key_title];
      for ($ii = 0; $ii < count($lines); $ii++) {
        if (strlen(trim($lines[$ii])) == 0) {
          break;
        }
      }
      if ($ii != count($lines)) {
        $fields[$key_title] = array_slice($lines, 0, $ii);
        $summary = array_slice($lines, $ii);
        if (strlen(trim(implode("\n", $summary)))) {
          $fields[$key_summary] = $summary;
        }
      }
    }

    // Implode all the lines back into chunks of text.
    foreach ($fields as $name => $lines) {
      $data = rtrim(implode("\n", $lines));
      $data = ltrim($data, "\n");
      $fields[$name] = $data;
    }

    // This is another piece of special-cased magic which allows you to
    // enter a ridiculously long title, or just type a big block of stream
    // of consciousness text, and have some sort of reasonable result conjured
    // from it.
    if (isset($fields[$key_title])) {
      $terminal = '...';
      $title = $fields[$key_title];
      $short = id(new PhutilUTF8StringTruncator())
        ->setMaximumBytes(250)
        ->setTerminator($terminal)
        ->truncateString($title);

      if ($short != $title) {

        // If we shortened the title, split the rest into the summary, so
        // we end up with a title like:
        //
        //    Title title tile title title...
        //
        // ...and a summary like:
        //
        //    ...title title title.
        //
        //    Summary summary summary summary.

        $summary = idx($fields, $key_summary, '');
        $offset = strlen($short) - strlen($terminal);
        $remainder = ltrim(substr($fields[$key_title], $offset));
        $summary = '...'.$remainder."\n\n".$summary;
        $summary = rtrim($summary, "\n");

        $fields[$key_title] = $short;
        $fields[$key_summary] = $summary;
      }
    }

    return $fields;
  }


  /**
   * @task parse
   */
  public function getErrors() {
    return $this->errors;
  }


/* -(  Support Methods  )---------------------------------------------------- */


  /**
   * @task support
   */
  public static function normalizeFieldLabel($label) {
    return phutil_utf8_strtolower($label);
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  private function buildLabelRegexp(array $label_map) {
    $field_labels = array_keys($label_map);
    foreach ($field_labels as $key => $label) {
      $field_labels[$key] = preg_quote($label, '/');
    }
    $field_labels = implode('|', $field_labels);

    $field_pattern = '/^(?P<field>'.$field_labels.'):(?P<text>.*)$/i';

    return $field_pattern;
  }

}
