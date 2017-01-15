<?php

abstract class DifferentialHunk extends DifferentialDAO
  implements PhabricatorPolicyInterface {

  protected $changesetID;
  protected $oldOffset;
  protected $oldLen;
  protected $newOffset;
  protected $newLen;

  private $changeset;
  private $splitLines;
  private $structuredLines;
  private $structuredFiles = array();

  const FLAG_LINES_ADDED     = 1;
  const FLAG_LINES_REMOVED   = 2;
  const FLAG_LINES_STABLE    = 4;

  public function getAddedLines() {
    return $this->makeContent($include = '+');
  }

  public function getRemovedLines() {
    return $this->makeContent($include = '-');
  }

  public function makeNewFile() {
    return implode('', $this->makeContent($include = ' +'));
  }

  public function makeOldFile() {
    return implode('', $this->makeContent($include = ' -'));
  }

  public function makeChanges() {
    return implode('', $this->makeContent($include = '-+'));
  }

  public function getStructuredOldFile() {
    return $this->getStructuredFile('-');
  }

  public function getStructuredNewFile() {
    return $this->getStructuredFile('+');
  }

  private function getStructuredFile($kind) {
    if ($kind !== '+' && $kind !== '-') {
      throw new Exception(
        pht(
          'Structured file kind should be "+" or "-", got "%s".',
          $kind));
    }

    if (!isset($this->structuredFiles[$kind])) {
      if ($kind == '+') {
        $number = $this->newOffset;
      } else {
        $number = $this->oldOffset;
      }

      $lines = $this->getStructuredLines();

      // NOTE: We keep the "\ No newline at end of file" line if it appears
      // after a line which is not excluded. For example, if we're constructing
      // the "+" side of the diff, we want to ignore this one since it's
      // relevant only to the "-" side of the diff:
      //
      //    - x
      //    \ No newline at end of file
      //    + x
      //
      // ...but we want to keep this one:
      //
      //    - x
      //    + x
      //    \ No newline at end of file

      $file = array();
      $keep = true;
      foreach ($lines as $line) {
        switch ($line['type']) {
          case ' ':
          case $kind:
            $file[$number++] = $line;
            $keep = true;
            break;
          case '\\':
            if ($keep) {
              // Strip the actual newline off the line's text.
              $text = $file[$number - 1]['text'];
              $text = rtrim($text, "\r\n");
              $file[$number - 1]['text'] = $text;

              $file[$number++] = $line;
              $keep = false;
            }
            break;
          default:
            $keep = false;
            break;
        }
      }

      $this->structuredFiles[$kind] = $file;
    }

    return $this->structuredFiles[$kind];
  }

  public function getSplitLines() {
    if ($this->splitLines === null) {
      $this->splitLines = phutil_split_lines($this->getChanges());
    }
    return $this->splitLines;
  }

  public function getStructuredLines() {
    if ($this->structuredLines === null) {
      $lines = $this->getSplitLines();

      $structured = array();
      foreach ($lines as $line) {
        if (empty($line[0])) {
          // TODO: Can we just get rid of this?
          continue;
        }

        $structured[] = array(
          'type' => $line[0],
          'text' => substr($line, 1),
        );
      }

      $this->structuredLines = $structured;
    }

    return $this->structuredLines;
  }


  public function getContentWithMask($mask) {
    $include = array();

    if (($mask & self::FLAG_LINES_ADDED)) {
      $include[] = '+';
    }

    if (($mask & self::FLAG_LINES_REMOVED)) {
      $include[] = '-';
    }

    if (($mask & self::FLAG_LINES_STABLE)) {
      $include[] = ' ';
    }

    $include = implode('', $include);

    return implode('', $this->makeContent($include));
  }

  final private function makeContent($include) {
    $lines = $this->getSplitLines();
    $results = array();

    $include_map = array();
    for ($ii = 0; $ii < strlen($include); $ii++) {
      $include_map[$include[$ii]] = true;
    }

    if (isset($include_map['+'])) {
      $n = $this->newOffset;
    } else {
      $n = $this->oldOffset;
    }

    $use_next_newline = false;
    foreach ($lines as $line) {
      if (!isset($line[0])) {
        continue;
      }

      if ($line[0] == '\\') {
        if ($use_next_newline) {
          $results[last_key($results)] = rtrim(end($results), "\n");
        }
      } else if (empty($include_map[$line[0]])) {
        $use_next_newline = false;
      } else {
        $use_next_newline = true;
        $results[$n] = substr($line, 1);
      }

      if ($line[0] == ' ' || isset($include_map[$line[0]])) {
        $n++;
      }
    }

    return $results;
  }

  public function getChangeset() {
    return $this->assertAttached($this->changeset);
  }

  public function attachChangeset(DifferentialChangeset $changeset) {
    $this->changeset = $changeset;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getChangeset()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getChangeset()->hasAutomaticCapability($capability, $viewer);
  }

}
