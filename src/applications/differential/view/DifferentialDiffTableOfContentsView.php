<?php

final class DifferentialDiffTableOfContentsView extends AphrontView {

  private $changesets = array();
  private $visibleChangesets = array();
  private $references = array();
  private $repository;
  private $diff;
  private $user;
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

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
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

    require_celerity_resource('differential-core-view-css');
    require_celerity_resource('differential-table-of-contents-css');

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
      $link = $this->renderChangesetLink($changeset, $ref);

      if (DifferentialChangeType::isOldLocationChangeType($type)) {
        $away = $changeset->getAwayPaths();
        if (count($away) > 1) {
          $meta = array();
          if ($type == DifferentialChangeType::TYPE_MULTICOPY) {
            $meta[] = 'Deleted after being copied to multiple locations:';
          } else {
            $meta[] = 'Copied to multiple locations:';
          }
          foreach ($away as $path) {
            $meta[] = phutil_escape_html($path);
          }
          $meta = implode('<br />', $meta);
        } else {
          if ($type == DifferentialChangeType::TYPE_MOVE_AWAY) {
            $meta = 'Moved to '.phutil_escape_html(reset($away));
          } else {
            $meta = 'Copied to '.phutil_escape_html(reset($away));
          }
        }
      } else if ($type == DifferentialChangeType::TYPE_MOVE_HERE) {
        $meta = 'Moved from '.phutil_escape_html($changeset->getOldFile());
      } else if ($type == DifferentialChangeType::TYPE_COPY_HERE) {
        $meta = 'Copied from '.phutil_escape_html($changeset->getOldFile());
      } else {
        $meta = null;
      }

      $line_count = $changeset->getAffectedLineCount();
      if ($line_count == 0) {
        $lines = null;
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
          ? null
          : '<span title="Properties Changed">M</span>';

      $fname = $changeset->getFilename();
      $cov  = $this->renderCoverage($coverage, $fname);
      if ($cov === null) {
        $mcov = $cov = '<em>-</em>';
      } else {
        $mcov = phutil_render_tag(
          'div',
          array(
            'id' => 'differential-mcoverage-'.md5($fname),
            'class' => 'differential-mcoverage-loading',
          ),
          (isset($this->visibleChangesets[$id]) ? 'Loading...' : '?'));
      }

      $rows[] =
        '<tr>'.
          phutil_render_tag(
            'td',
            array(
              'class' => 'differential-toc-char',
              'title' => $chartitle,
            ),
            $char).
          '<td class="differential-toc-prop">'.$pchar.'</td>'.
          '<td class="differential-toc-ftype">'.$desc.'</td>'.
          '<td class="differential-toc-file">'.$link.$lines.'</td>'.
          '<td class="differential-toc-cov">'.$cov.'</td>'.
          '<td class="differential-toc-mcov">'.$mcov.'</td>'.
        '</tr>';
      if ($meta) {
        $rows[] =
          '<tr>'.
            '<td colspan="3"></td>'.
            '<td class="differential-toc-meta">'.$meta.'</td>'.
          '</tr>';
      }
      if ($this->diff && $this->repository) {
        $paths[] =
          $changeset->getAbsoluteRepositoryPath($this->repository, $this->diff);
      }
    }

    $editor_link = null;
    if ($paths && $this->user) {
      $editor_link = $this->user->loadEditorLink(
        implode(' ', $paths),
        1, // line number
        $this->repository->getCallsign());
      if ($editor_link) {
        $editor_link = phutil_render_tag(
          'a',
          array(
            'href' => $editor_link,
            'class' => 'button differential-toc-edit-all',
          ),
          'Open All in Editor');
      }
    }

    $reveal_link = javelin_render_tag(
      'a',
      array(
        'sigil' => 'differential-reveal-all',
        'mustcapture' => true,
        'class' => 'button differential-toc-reveal-all',
      ),
      'Show All Context'
    );

    return
      id(new PhabricatorAnchorView())
        ->setAnchorName('toc')
        ->setNavigationMarker(true)
        ->render().
      '<div class="differential-toc differential-panel">'.
        $editor_link.
        $reveal_link.
        '<h1>Table of Contents</h1>'.
        '<table>'.
          '<tr>'.
            '<th></th>'.
            '<th></th>'.
            '<th></th>'.
            '<th>Path</th>'.
            '<th class="differential-toc-cov">Coverage (All)</th>'.
            '<th class="differential-toc-mcov">Coverage (Touched)</th>'.
          '</tr>'.
          implode("\n", $rows).
        '</table>'.
      '</div>';
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


  private function renderChangesetLink(DifferentialChangeset $changeset, $ref) {
    $display_file = $changeset->getDisplayFilename();

    return javelin_render_tag(
      'a',
      array(
        'href' => '#'.$changeset->getAnchorName(),
        'meta' => array(
          'id' => 'diff-'.$changeset->getAnchorName(),
          'ref' => $ref,
        ),
        'sigil' => 'differential-load',
      ),
      phutil_escape_html($display_file));
  }

}
