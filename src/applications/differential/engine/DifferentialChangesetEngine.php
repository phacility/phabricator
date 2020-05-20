<?php

final class DifferentialChangesetEngine extends Phobject {

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function rebuildChangesets(array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');

    $changesets = $this->loadChangesetFiles($changesets);

    foreach ($changesets as $changeset) {
      $this->detectGeneratedCode($changeset);
      $this->computeHashes($changeset);
    }

    $this->detectCopiedCode($changesets);
  }

  private function loadChangesetFiles(array $changesets) {
    $viewer = $this->getViewer();

    $file_phids = array();
    foreach ($changesets as $changeset) {
      $file_phid = $changeset->getNewFileObjectPHID();
      if ($file_phid !== null) {
        $file_phids[] = $file_phid;
      }
    }

    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs($file_phids)
        ->execute();
      $files = mpull($files, null, 'getPHID');
    } else {
      $files = array();
    }

    foreach ($changesets as $changeset_key => $changeset) {
      $file_phid = $changeset->getNewFileObjectPHID();
      if ($file_phid === null) {
        continue;
      }

      $file = idx($files, $file_phid);
      if (!$file) {
        unset($changesets[$changeset_key]);
        continue;
      }

      $changeset->attachNewFileObject($file);
    }

    return $changesets;
  }


/* -(  Generated Code  )----------------------------------------------------- */


  private function detectGeneratedCode(DifferentialChangeset $changeset) {
    $is_generated_trusted = $this->isTrustedGeneratedCode($changeset);
    if ($is_generated_trusted) {
      $changeset->setTrustedChangesetAttribute(
        DifferentialChangeset::ATTRIBUTE_GENERATED,
        $is_generated_trusted);
    }

    $is_generated_untrusted = $this->isUntrustedGeneratedCode($changeset);
    if ($is_generated_untrusted) {
      $changeset->setUntrustedChangesetAttribute(
        DifferentialChangeset::ATTRIBUTE_GENERATED,
        $is_generated_untrusted);
    }
  }

  private function isTrustedGeneratedCode(DifferentialChangeset $changeset) {

    $filename = $changeset->getFilename();

    $paths = PhabricatorEnv::getEnvConfig('differential.generated-paths');
    foreach ($paths as $regexp) {
      if (preg_match($regexp, $filename)) {
        return true;
      }
    }

    return false;
  }

  private function isUntrustedGeneratedCode(DifferentialChangeset $changeset) {

    if ($changeset->getHunks()) {
      $new_data = $changeset->makeNewFile();
      if (strpos($new_data, '@'.'generated') !== false) {
        return true;
      }

      // See PHI1112. This is the official pattern for marking Go code as
      // generated.
      if (preg_match('(^// Code generated .* DO NOT EDIT\.$)m', $new_data)) {
        return true;
      }
    }

    return false;
  }


/* -(  Content Hashes  )----------------------------------------------------- */


  private function computeHashes(DifferentialChangeset $changeset) {

    $effect_key = DifferentialChangeset::METADATA_EFFECT_HASH;

    $effect_hash = $this->newEffectHash($changeset);
    if ($effect_hash !== null) {
      $changeset->setChangesetMetadata($effect_key, $effect_hash);
    }
  }

  private function newEffectHash(DifferentialChangeset $changeset) {

    if ($changeset->getHunks()) {
      $new_data = $changeset->makeNewFile();
      return PhabricatorHash::digestForIndex($new_data);
    }

    if ($changeset->getNewFileObjectPHID()) {
      $file = $changeset->getNewFileObject();

      // See T13522. For now, the "contentHash" is not really a content hash
      // for files >4MB. This is okay: we will just always detect them as
      // changed, which is the safer behavior.

      $hash = $file->getContentHash();
      if ($hash !== null) {
        $hash = sprintf('file-hash:%s', $hash);
        return PhabricatorHash::digestForIndex($hash);
      }
    }

    return null;
  }


/* -(  Copied Code  )-------------------------------------------------------- */


  private function detectCopiedCode(array $changesets) {
    // See PHI944. If the total number of changed lines is excessively large,
    // don't bother with copied code detection. This can take a lot of time and
    // memory and it's not generally of any use for very large changes.
    $max_size = 65535;

    $total_size = 0;
    foreach ($changesets as $changeset) {
      $total_size += ($changeset->getAddLines() + $changeset->getDelLines());
    }

    if ($total_size > $max_size) {
      return;
    }

    $min_width = 30;
    $min_lines = 3;

    $map = array();
    $files = array();
    $types = array();
    foreach ($changesets as $changeset) {
      $file = $changeset->getFilename();
      foreach ($changeset->getHunks() as $hunk) {
        $lines = $hunk->getStructuredOldFile();
        foreach ($lines as $line => $info) {
          $type = $info['type'];
          if ($type == '\\') {
            continue;
          }
          $types[$file][$line] = $type;

          $text = $info['text'];
          $text = trim($text);
          $files[$file][$line] = $text;

          if (strlen($text) >= $min_width) {
            $map[$text][] = array($file, $line);
          }
        }
      }
    }

    foreach ($changesets as $changeset) {
      $copies = array();
      foreach ($changeset->getHunks() as $hunk) {
        $added = $hunk->getStructuredNewFile();
        $atype = array();

        foreach ($added as $line => $info) {
          $atype[$line] = $info['type'];
          $added[$line] = trim($info['text']);
        }

        $skip_lines = 0;
        foreach ($added as $line => $code) {
          if ($skip_lines) {
            // We're skipping lines that we already processed because we
            // extended a block above them downward to include them.
            $skip_lines--;
            continue;
          }

          if ($atype[$line] !== '+') {
            // This line hasn't been changed in the new file, so don't try
            // to figure out where it came from.
            continue;
          }

          if (empty($map[$code])) {
            // This line was too short to trigger copy/move detection.
            continue;
          }

          if (count($map[$code]) > 16) {
            // If there are a large number of identical lines in this diff,
            // don't try to figure out where this block came from: the analysis
            // is O(N^2), since we need to compare every line against every
            // other line. Even if we arrive at a result, it is unlikely to be
            // meaningful. See T5041.
            continue;
          }

          $best_length = 0;

          // Explore all candidates.
          foreach ($map[$code] as $val) {
            list($file, $orig_line) = $val;
            $length = 1;

            // Search backward and forward to find all of the adjacent lines
            // which match.
            foreach (array(-1, 1) as $direction) {
              $offset = $direction;
              while (true) {
                if (isset($copies[$line + $offset])) {
                  // If we run into a block above us which we've already
                  // attributed to a move or copy from elsewhere, stop
                  // looking.
                  break;
                }

                if (!isset($added[$line + $offset])) {
                  // If we've run off the beginning or end of the new file,
                  // stop looking.
                  break;
                }

                if (!isset($files[$file][$orig_line + $offset])) {
                  // If we've run off the beginning or end of the original
                  // file, we also stop looking.
                  break;
                }

                $old = $files[$file][$orig_line + $offset];
                $new = $added[$line + $offset];
                if ($old !== $new) {
                  // If the old line doesn't match the new line, stop
                  // looking.
                  break;
                }

                $length++;
                $offset += $direction;
              }
            }

            if ($length < $best_length) {
              // If we already know of a better source (more matching lines)
              // for this move/copy, stick with that one. We prefer long
              // copies/moves which match a lot of context over short ones.
              continue;
            }

            if ($length == $best_length) {
              if (idx($types[$file], $orig_line) != '-') {
                // If we already know of an equally good source (same number
                // of matching lines) and this isn't a move, stick with the
                // other one. We prefer moves over copies.
                continue;
              }
            }

            $best_length = $length;
            // ($offset - 1) contains number of forward matching lines.
            $best_offset = $offset - 1;
            $best_file = $file;
            $best_line = $orig_line;
          }

          $file = ($best_file == $changeset->getFilename() ? '' : $best_file);
          for ($i = $best_length; $i--; ) {
            $type = idx($types[$best_file], $best_line + $best_offset - $i);
            $copies[$line + $best_offset - $i] = ($best_length < $min_lines
              ? array() // Ignore short blocks.
              : array($file, $best_line + $best_offset - $i, $type));
          }

          $skip_lines = $best_offset;
        }
      }

      $copies = array_filter($copies);
      if ($copies) {
        $metadata = $changeset->getMetadata();
        $metadata['copy:lines'] = $copies;
        $changeset->setMetadata($metadata);
      }
    }

  }

}
