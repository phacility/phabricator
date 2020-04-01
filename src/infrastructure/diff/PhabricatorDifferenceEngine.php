<?php

/**
 * Utility class which encapsulates some shared behavior between different
 * applications which render diffs.
 *
 * @task config Configuring the Engine
 * @task diff Generating Diffs
 */
final class PhabricatorDifferenceEngine extends Phobject {


  private $oldName;
  private $newName;
  private $normalize;


/* -(  Configuring the Engine  )--------------------------------------------- */


  /**
   * Set the name to identify the old file with. Primarily cosmetic.
   *
   * @param  string Old file name.
   * @return this
   * @task config
   */
  public function setOldName($old_name) {
    $this->oldName = $old_name;
    return $this;
  }


  /**
   * Set the name to identify the new file with. Primarily cosmetic.
   *
   * @param  string New file name.
   * @return this
   * @task config
   */
  public function setNewName($new_name) {
    $this->newName = $new_name;
    return $this;
  }


  public function setNormalize($normalize) {
    $this->normalize = $normalize;
    return $this;
  }

  public function getNormalize() {
    return $this->normalize;
  }


/* -(  Generating Diffs  )--------------------------------------------------- */


  /**
   * Generate a raw diff from two raw files. This is a lower-level API than
   * @{method:generateChangesetFromFileContent}, but may be useful if you need
   * to use a custom parser configuration, as with Diffusion.
   *
   * @param string Entire previous file content.
   * @param string Entire current file content.
   * @return string Raw diff between the two files.
   * @task diff
   */
  public function generateRawDiffFromFileContent($old, $new) {

    $options = array();

    // Generate diffs with full context.
    $options[] = '-U65535';

    $old_name = nonempty($this->oldName, '/dev/universe').' 9999-99-99';
    $new_name = nonempty($this->newName, '/dev/universe').' 9999-99-99';

    $options[] = '-L';
    $options[] = $old_name;
    $options[] = '-L';
    $options[] = $new_name;

    $normalize = $this->getNormalize();
    if ($normalize) {
      $old = $this->normalizeFile($old);
      $new = $this->normalizeFile($new);
    }

    $old_tmp = new TempFile();
    $new_tmp = new TempFile();

    Filesystem::writeFile($old_tmp, $old);
    Filesystem::writeFile($new_tmp, $new);
    list($err, $diff) = exec_manual(
      'diff %Ls %s %s',
      $options,
      $old_tmp,
      $new_tmp);

    if (!$err) {
      // This indicates that the two files are the same. Build a synthetic,
      // changeless diff so that we can still render the raw, unchanged file
      // instead of being forced to just say "this file didn't change" since we
      // don't have the content.

      $entire_file = explode("\n", $old);
      foreach ($entire_file as $k => $line) {
        $entire_file[$k] = ' '.$line;
      }

      $len = count($entire_file);
      $entire_file = implode("\n", $entire_file);

      // TODO: If both files were identical but missing newlines, we probably
      // get this wrong. Unclear if it ever matters.

      // This is a bit hacky but the diff parser can handle it.
      $diff = "--- {$old_name}\n".
              "+++ {$new_name}\n".
              "@@ -1,{$len} +1,{$len} @@\n".
              $entire_file."\n";
    }

    return $diff;
  }


  /**
   * Generate an @{class:DifferentialChangeset} from two raw files. This is
   * principally useful because you can feed the output to
   * @{class:DifferentialChangesetParser} in order to render it.
   *
   * @param string Entire previous file content.
   * @param string Entire current file content.
   * @return @{class:DifferentialChangeset} Synthetic changeset.
   * @task diff
   */
  public function generateChangesetFromFileContent($old, $new) {
    $diff = $this->generateRawDiffFromFileContent($old, $new);

    $changes = id(new ArcanistDiffParser())->parseDiff($diff);
    $diff = DifferentialDiff::newEphemeralFromRawChanges(
      $changes);
    return head($diff->getChangesets());
  }

  private function normalizeFile($corpus) {
    // We can freely apply any other transformations we want to here: we have
    // no constraints on what we need to preserve. If we normalize every line
    // to "cat", the diff will still work, the alignment of the "-" / "+"
    // lines will just be very hard to read.

    // In general, we'll make the diff better if we normalize two lines that
    // humans think are the same.

    // We'll make the diff worse if we normalize two lines that humans think
    // are different.


    // Strip all whitespace present anywhere in the diff, since humans never
    // consider whitespace changes to alter the line into a "different line"
    // even when they're semantic (e.g., in a string constant). This covers
    // indentation changes, trailing whitepspace, and formatting changes
    // like "+/-".
    $corpus = preg_replace('/[ \t]/', '', $corpus);

    return $corpus;
  }

  public static function applyIntralineDiff($str, $intra_stack) {
    $buf = '';
    $p = $s = $e = 0; // position, start, end
    $highlight = $tag = $ent = false;
    $highlight_o = '<span class="bright">';
    $highlight_c = '</span>';

    $depth_in = '<span class="depth-in">';
    $depth_out = '<span class="depth-out">';

    $is_html = false;
    if ($str instanceof PhutilSafeHTML) {
      $is_html = true;
      $str = $str->getHTMLContent();
    }

    $n = strlen($str);
    for ($i = 0; $i < $n; $i++) {

      if ($p == $e) {
        do {
          if (empty($intra_stack)) {
            $buf .= substr($str, $i);
            break 2;
          }
          $stack = array_shift($intra_stack);
          $s = $e;
          $e += $stack[1];
        } while ($stack[0] === 0);

        switch ($stack[0]) {
          case '>':
            $open_tag = $depth_in;
            break;
          case '<':
            $open_tag = $depth_out;
            break;
          default:
            $open_tag = $highlight_o;
            break;
        }
      }

      if (!$highlight && !$tag && !$ent && $p == $s) {
        $buf .= $open_tag;
        $highlight = true;
      }

      if ($str[$i] == '<') {
        $tag = true;
        if ($highlight) {
          $buf .= $highlight_c;
        }
      }

      if (!$tag) {
        if ($str[$i] == '&') {
          $ent = true;
        }
        if ($ent && $str[$i] == ';') {
          $ent = false;
        }
        if (!$ent) {
          $p++;
        }
      }

      $buf .= $str[$i];

      if ($tag && $str[$i] == '>') {
        $tag = false;
        if ($highlight) {
          $buf .= $open_tag;
        }
      }

      if ($highlight && ($p == $e || $i == $n - 1)) {
        $buf .= $highlight_c;
        $highlight = false;
      }
    }

    if ($is_html) {
      return phutil_safe_html($buf);
    }

    return $buf;
  }

}
