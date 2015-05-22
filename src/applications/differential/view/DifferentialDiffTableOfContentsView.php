<?php

final class DifferentialDiffTableOfContentsView extends AphrontView {

  private $changesets = array();
  private $visibleChangesets = array();
  private $references = array();
  private $repository;
  private $diff;
  private $renderURI = '/differential/changeset/';
  private $revisionID;
  private $whitespace;
  private $unitTestData;

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function setVisibleChangesets($visible_changesets) {
    $this->visibleChangesets = $visible_changesets;
    return $this;
  }

  public function setRenderingReferences(array $references) {
    $this->references = $references;
    return $this;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function setUnitTestData($unit_test_data) {
    $this->unitTestData = $unit_test_data;
    return $this;
  }

  public function setRevisionID($revision_id) {
    $this->revisionID = $revision_id;
    return $this;
  }

  public function setWhitespace($whitespace) {
    $this->whitespace = $whitespace;
    return $this;
  }

  public function render() {

    $this->requireResource('differential-core-view-css');
    $this->requireResource('differential-table-of-contents-css');

    $rows = array();

    $coverage = array();
    if ($this->unitTestData) {
      $coverage_by_file = array();
      foreach ($this->unitTestData as $result) {
        $test_coverage = idx($result, 'coverage');
        if (!$test_coverage) {
          continue;
        }
        foreach ($test_coverage as $file => $results) {
          $coverage_by_file[$file][] = $results;
        }
      }
      foreach ($coverage_by_file as $file => $coverages) {
        $coverage[$file] = ArcanistUnitTestResult::mergeCoverage($coverages);
      }
    }

    $changesets = $this->changesets;
    $paths = array();
    foreach ($changesets as $id => $changeset) {
      $type = $changeset->getChangeType();
      $ftype = $changeset->getFileType();
      $ref = idx($this->references, $id);
      $display_file = $changeset->getDisplayFilename();

      $meta = null;
      if (DifferentialChangeType::isOldLocationChangeType($type)) {
        $away = $changeset->getAwayPaths();
        if (count($away) > 1) {
          $meta = array();
          if ($type == DifferentialChangeType::TYPE_MULTICOPY) {
            $meta[] = pht('Deleted after being copied to multiple locations:');
          } else {
            $meta[] = pht('Copied to multiple locations:');
          }
          foreach ($away as $path) {
            $meta[] = $path;
          }
          $meta = phutil_implode_html(phutil_tag('br'), $meta);
        } else {
          if ($type == DifferentialChangeType::TYPE_MOVE_AWAY) {
            $display_file = $this->renderRename(
              $display_file,
              reset($away),
              "\xE2\x86\x92");
          } else {
            $meta = pht('Copied to %s', reset($away));
          }
        }
      } else if ($type == DifferentialChangeType::TYPE_MOVE_HERE) {
        $old_file = $changeset->getOldFile();
        $display_file = $this->renderRename(
          $display_file,
          $old_file,
          "\xE2\x86\x90");
      } else if ($type == DifferentialChangeType::TYPE_COPY_HERE) {
        $meta = pht('Copied from %s', $changeset->getOldFile());
      }

      $link = $this->renderChangesetLink($changeset, $ref, $display_file);

      $line_count = $changeset->getAffectedLineCount();
      if ($line_count == 0) {
        $lines = '';
      } else {
        $lines = ' '.pht('(%d line(s))', $line_count);
      }

      $char = DifferentialChangeType::getSummaryCharacterForChangeType($type);
      $chartitle = DifferentialChangeType::getFullNameForChangeType($type);
      $desc = DifferentialChangeType::getShortNameForFileType($ftype);
      if ($desc) {
        $desc = '('.$desc.')';
      }
      $pchar =
        ($changeset->getOldProperties() === $changeset->getNewProperties())
          ? ''
          : phutil_tag(
            'span',
            array('title' => pht('Properties Changed')),
            'M');

      $fname = $changeset->getFilename();
      $cov  = $this->renderCoverage($coverage, $fname);
      if ($cov === null) {
        $mcov = $cov = phutil_tag('em', array(), '-');
      } else {
        $mcov = phutil_tag(
          'div',
          array(
            'id' => 'differential-mcoverage-'.md5($fname),
            'class' => 'differential-mcoverage-loading',
          ),
          (isset($this->visibleChangesets[$id]) ?
            pht('Loading...') : pht('?')));
      }

      if ($meta) {
        $meta = phutil_tag(
          'div',
          array(
            'class' => 'differential-toc-meta',
          ),
          $meta);
      }

      if ($this->diff && $this->repository) {
        $paths[] =
          $changeset->getAbsoluteRepositoryPath($this->repository, $this->diff);
      }

      $rows[] = array(
        $char,
        $pchar,
        $desc,
        array($link, $lines, $meta),
        $cov,
        $mcov,
      );
    }

    $editor_link = null;
    if ($paths && $this->user) {
      $editor_link = $this->user->loadEditorLink(
        $paths,
        1, // line number
        $this->repository->getCallsign());
      if ($editor_link) {
        $editor_link =
          phutil_tag(
            'a',
            array(
              'href' => $editor_link,
              'class' => 'button differential-toc-edit-all',
            ),
            pht('Open All in Editor'));
      }
    }

    $reveal_link = javelin_tag(
        'a',
        array(
          'sigil' => 'differential-reveal-all',
          'mustcapture' => true,
          'class' => 'button differential-toc-reveal-all',
        ),
        pht('Show All Context'));

    $buttons = phutil_tag(
      'div',
      array(
        'class' => 'differential-toc-buttons grouped',
      ),
      array(
        $editor_link,
        $reveal_link,
      ));

    $table = id(new AphrontTableView($rows));
    $table->setHeaders(
      array(
        '',
        '',
        '',
        pht('Path'),
        pht('Coverage (All)'),
        pht('Coverage (Touched)'),
      ));
    $table->setColumnClasses(
      array(
        'differential-toc-char center',
        'differential-toc-prop center',
        'differential-toc-ftype center',
        'differential-toc-file wide',
        'differential-toc-cov',
        'differential-toc-cov',
      ));
    $table->setDeviceVisibility(
      array(
        true,
        true,
        true,
        true,
        false,
        false,
      ));
    $anchor = id(new PhabricatorAnchorView())
      ->setAnchorName('toc')
      ->setNavigationMarker(true);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Table of Contents'))
      ->appendChild($anchor)
      ->appendChild($table)
      ->appendChild($buttons);
  }

  private function renderRename($display_file, $other_file, $arrow) {
    $old = explode('/', $display_file);
    $new = explode('/', $other_file);

    $start = count($old);
    foreach ($old as $index => $part) {
      if (!isset($new[$index]) || $part != $new[$index]) {
        $start = $index;
        break;
      }
    }

    $end = count($old);
    foreach (array_reverse($old) as $from_end => $part) {
      $index = count($new) - $from_end - 1;
      if (!isset($new[$index]) || $part != $new[$index]) {
        $end = $from_end;
        break;
      }
    }

    $rename =
      '{'.
      implode('/', array_slice($old, $start, count($old) - $end - $start)).
      ' '.$arrow.' '.
      implode('/', array_slice($new, $start, count($new) - $end - $start)).
      '}';

    array_splice($new, $start, count($new) - $end - $start, $rename);
    return implode('/', $new);
  }

  private function renderCoverage(array $coverage, $file) {
    $info = idx($coverage, $file);
    if (!$info) {
      return null;
    }

    $not_covered = substr_count($info, 'U');
    $covered     = substr_count($info, 'C');

    if (!$not_covered && !$covered) {
      return null;
    }

    return sprintf('%d%%', 100 * ($covered / ($covered + $not_covered)));
  }


  private function renderChangesetLink(
    DifferentialChangeset $changeset,
    $ref,
    $display_file) {

    return javelin_tag(
      'a',
      array(
        'href' => '#'.$changeset->getAnchorName(),
        'sigil' => 'differential-load',
        'meta' => array(
          'id' => 'diff-'.$changeset->getAnchorName(),
        ),
      ),
      $display_file);
  }

}
