<?php

final class DifferentialChangeset extends DifferentialDAO {

  protected $diffID;
  protected $oldFile;
  protected $filename;
  protected $awayPaths;
  protected $changeType;
  protected $fileType;
  protected $metadata;
  protected $oldProperties;
  protected $newProperties;
  protected $addLines;
  protected $delLines;

  private $unsavedHunks = array();
  private $hunks;

  const TABLE_CACHE = 'differential_changeset_parse_cache';

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata'      => self::SERIALIZATION_JSON,
        'oldProperties' => self::SERIALIZATION_JSON,
        'newProperties' => self::SERIALIZATION_JSON,
        'awayPaths'     => self::SERIALIZATION_JSON,
      )) + parent::getConfiguration();
  }

  public function getAffectedLineCount() {
    return $this->getAddLines() + $this->getDelLines();
  }

  public function attachHunks(array $hunks) {
    assert_instances_of($hunks, 'DifferentialHunk');
    $this->hunks = $hunks;
    return $this;
  }

  public function getHunks() {
    if ($this->hunks === null) {
      throw new Exception("Must load and attach hunks first!");
    }
    return $this->hunks;
  }

  public function getDisplayFilename() {
    $name = $this->getFilename();
    if ($this->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
      $name .= '/';
    }
    return $name;
  }

  public function addUnsavedHunk(DifferentialHunk $hunk) {
    if ($this->hunks === null) {
      $this->hunks = array();
    }
    $this->hunks[] = $hunk;
    $this->unsavedHunks[] = $hunk;
    return $this;
  }

  public function loadHunks() {
    if (!$this->getID()) {
      return array();
    }
    return id(new DifferentialHunk())->loadAllWhere(
      'changesetID = %d',
      $this->getID());
  }

  public function save() {
    $this->openTransaction();
      $ret = parent::save();
      foreach ($this->unsavedHunks as $hunk) {
        $hunk->setChangesetID($this->getID());
        $hunk->save();
      }
    $this->saveTransaction();
    return $ret;
  }

  public function delete() {
    $this->openTransaction();
      foreach ($this->loadHunks() as $hunk) {
        $hunk->delete();
      }
      $this->_hunks = array();

      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE id = %d',
        self::TABLE_CACHE,
        $this->getID());

      $ret = parent::delete();
    $this->saveTransaction();
    return $ret;
  }

  public function getSortKey() {
    $sort_key = $this->getFilename();
    // Sort files with ".h" in them first, so headers (.h, .hpp) come before
    // implementations (.c, .cpp, .cs).
    $sort_key = str_replace('.h', '.!h', $sort_key);
    return $sort_key;
  }

  public function makeNewFile() {
    $file = mpull($this->getHunks(), 'makeNewFile');
    return implode('', $file);
  }

  public function makeOldFile() {
    $file = mpull($this->getHunks(), 'makeOldFile');
    return implode('', $file);
  }

  public function computeOffsets() {
    $offsets = array();
    $n = 1;
    foreach ($this->getHunks() as $hunk) {
      for ($i = 0; $i < $hunk->getNewLen(); $i++) {
        $offsets[$n] = $hunk->getNewOffset() + $i;
        $n++;
      }
    }
    return $offsets;
  }

  public function makeChangesWithContext($num_lines = 3) {
    $with_context = array();
    foreach ($this->getHunks() as $hunk) {
      $context = array();
      $changes = explode("\n", $hunk->getChanges());
      foreach ($changes as $l => $line) {
        if ($line[0] == '+' || $line[0] == '-') {
          $context += array_fill($l - $num_lines, 2 * $num_lines + 1, true);
        }
      }
      $with_context[] = array_intersect_key($changes, $context);
    }
    return array_mergev($with_context);
  }

  public function getAnchorName() {
    return substr(md5($this->getFilename()), 0, 8);
  }

  public function getAbsoluteRepositoryPath(
    PhabricatorRepository $repository = null,
    DifferentialDiff $diff = null) {

    $base = '/';
    if ($diff && $diff->getSourceControlPath()) {
      $base = id(new PhutilURI($diff->getSourceControlPath()))->getPath();
    }

    $path = $this->getFilename();
    $path = rtrim($base, '/').'/'.ltrim($path, '/');

    $svn = PhabricatorRepositoryType::REPOSITORY_TYPE_SVN;
    if ($repository && $repository->getVersionControlSystem() == $svn) {
      $prefix = $repository->getDetail('remote-uri');
      $prefix = id(new PhutilURI($prefix))->getPath();
      if (!strncmp($path, $prefix, strlen($prefix))) {
        $path = substr($path, strlen($prefix));
      }
      $path = '/'.ltrim($path, '/');
    }

    return $path;
  }

  /**
   * Retreive the configured wordwrap width for this changeset.
   */
  public function getWordWrapWidth() {
    $config = PhabricatorEnv::getEnvConfig('differential.wordwrap');
    foreach ($config as $regexp => $width) {
      if (preg_match($regexp, $this->getFilename())) {
        return $width;
      }
    }
    return 80;
  }

  public function getWhitespaceMatters() {
    $config = PhabricatorEnv::getEnvConfig('differential.whitespace-matters');
    foreach ($config as $regexp) {
      if (preg_match($regexp, $this->getFilename())) {
        return true;
      }
    }

    return false;
  }

  public function makeContextDiff($inline, $add_context) {
    $context = array();
    $debug = false;
    if ($debug) {
      $context[] = 'Inline: '.$inline->getIsNewFile().' '.
        $inline->getLineNumber().' '.$inline->getLineLength();
      foreach ($this->getHunks() as $hunk) {
        $context[] = 'hunk: '.$hunk->getOldOffset().'-'.
          $hunk->getOldLen().'; '.$hunk->getNewOffset().'-'.$hunk->getNewLen();
        $context[] = $hunk->getChanges();
      }
    }

    if ($inline->getIsNewFile()) {
      $prefix = '+';
    } else {
      $prefix = '-';
    }
    foreach ($this->getHunks() as $hunk) {
      if ($inline->getIsNewFile()) {
        $offset = $hunk->getNewOffset();
        $length = $hunk->getNewLen();
      } else {
        $offset = $hunk->getOldOffset();
        $length = $hunk->getOldLen();
      }
      $start = $inline->getLineNumber() - $offset;
      $end = $start + $inline->getLineLength();
      // We need to go in if $start == $length, because the last line
      // might be a "\No newline at end of file" marker, which we want
      // to show if the additional context is > 0.
      if ($start <= $length && $end >= 0) {
        $start = $start - $add_context;
        $end = $end + $add_context;
        $hunk_content = array();
        $hunk_pos = array( "-" => 0, "+" => 0 );
        $hunk_offset = array( "-" => NULL, "+" => NULL );
        $hunk_last = array( "-" => NULL, "+" => NULL );
        foreach (explode("\n", $hunk->getChanges()) as $line) {
          $in_common = strncmp($line, " ", 1) === 0;
          $in_old = strncmp($line, "-", 1) === 0 || $in_common;
          $in_new = strncmp($line, "+", 1) === 0 || $in_common;
          $in_selected = strncmp($line, $prefix, 1) === 0;
          $skip = !$in_selected && !$in_common;
          if ($hunk_pos[$prefix] <= $end) {
            if ($start <= $hunk_pos[$prefix]) {
              if (!$skip || ($hunk_pos[$prefix] != $start &&
                             $hunk_pos[$prefix] != $end)) {
                if ($in_old) {
                  if ($hunk_offset["-"] === NULL) {
                    $hunk_offset["-"] = $hunk_pos["-"];
                  }
                  $hunk_last["-"] = $hunk_pos["-"];
                }
                if ($in_new) {
                  if ($hunk_offset["+"] === NULL) {
                    $hunk_offset["+"] = $hunk_pos["+"];
                  }
                  $hunk_last["+"] = $hunk_pos["+"];
                }

                $hunk_content[] = $line;
              }
            }
            if ($in_old) { ++$hunk_pos["-"]; }
            if ($in_new) { ++$hunk_pos["+"]; }
          }
        }
        if ($hunk_offset["-"] !== NULL || $hunk_offset["+"] !== NULL) {
          $header = "@@";
          if ($hunk_offset["-"] !== NULL) {
            $header .= " -" . ($hunk->getOldOffset() + $hunk_offset["-"]) .
              "," . ($hunk_last["-"]-$hunk_offset["-"]+1);
          }
          if ($hunk_offset["+"] !== NULL) {
            $header .= " +" . ($hunk->getNewOffset() + $hunk_offset["+"]) .
              "," . ($hunk_last["+"]-$hunk_offset["+"]+1);
          }
          $header .= " @@";
          $context[] = $header;
          $context[] = implode("\n", $hunk_content);
        }
      }
    }
    return implode("\n", $context);
  }
}
