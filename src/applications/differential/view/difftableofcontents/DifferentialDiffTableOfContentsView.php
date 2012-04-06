<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class DifferentialDiffTableOfContentsView extends AphrontView {

  private $changesets = array();
  private $visibleChangesets = array();
  private $repository;
  private $diff;
  private $user;
  private $standaloneViewLink = null;
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

  public function setStandaloneViewLink($standalone_view_link) {
    $this->standaloneViewLink = $standalone_view_link;
    return $this;
  }

  public function setVsMap(array $vs_map) {
    $this->vsMap = $vs_map;
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
      $link = $this->renderChangesetLink($changeset);

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
      } else if ($line_count == 1) {
        $lines = ' (1 line)';
      } else {
        $lines = ' ('.$line_count.' lines)';
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
          $changeset->getAbsoluteRepositoryPath($this->diff, $this->repository);
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

    return
      '<div id="differential-review-toc" '.
        'class="differential-toc differential-panel">'.
        $editor_link.
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


  private function renderChangesetLink(DifferentialChangeset $changeset) {
    $display_file = $changeset->getDisplayFilename();

    if ($this->standaloneViewLink) {
      $id = $changeset->getID();
      $vs_id = idx($this->vsMap, $id);

      $ref = $vs_id ? $id.'/'.$vs_id : $id;
      $detail_uri = new PhutilURI($this->renderURI);
      $detail_uri->setQueryParams(
        array(
          'ref'         => $ref,
          'whitespace'  => $this->whitespace,
          'revision_id' => $this->revisionID,
        ));

      return phutil_render_tag(
        'a',
        array(
          'href' => $detail_uri,
          'target'  => '_blank',
        ),
        phutil_escape_html($display_file));
    }
    return phutil_render_tag(
      'a',
      array(
        'href' => '#'.$changeset->getAnchorName(),
      ),
      phutil_escape_html($display_file));
  }

}
