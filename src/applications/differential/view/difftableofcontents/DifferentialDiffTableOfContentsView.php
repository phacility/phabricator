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
  private $standaloneViewLink = null;
  private $renderURI = '/differential/changeset/';
  private $revisionID;
  private $whitespace;

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
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

    $changesets = $this->changesets;
    foreach ($changesets as $changeset) {
      $file = $changeset->getFilename();
      $display_file = $changeset->getDisplayFilename();

      $type = $changeset->getChangeType();
      $ftype = $changeset->getFileType();

      if ($this->standaloneViewLink) {
        $id = $changeset->getID();
        if ($id) {
          $vs_id = idx($this->vsMap, $id);
        } else {
          $vs_id = null;
        }

        $ref = $vs_id ? $id.'/'.$vs_id : $id;
        $detail_uri = new PhutilURI($this->renderURI);
        $detail_uri->setQueryParams(
          array(
            'ref'         => $ref,
            'whitespace'  => $this->whitespace,
            'revision_id' => $this->revisionID,
          ));

        $link = phutil_render_tag(
          'a',
          array(
            'href' => $detail_uri,
            'target'  => '_blank',
          ),
          phutil_escape_html($display_file));
      } else {
        $link = phutil_render_tag(
          'a',
          array(
            'href' => '#'.$changeset->getAnchorName(),
          ),
          phutil_escape_html($display_file));
      }

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

      $rows[] =
        '<tr>'.
          '<td class="differential-toc-char" title='.$chartitle.'>'.$char.
          '</td>'.
          '<td class="differential-toc-prop">'.$pchar.'</td>'.
          '<td class="differential-toc-ftype">'.$desc.'</td>'.
          '<td class="differential-toc-file">'.$link.$lines.'</td>'.
        '</tr>';
      if ($meta) {
        $rows[] =
          '<tr>'.
            '<td colspan="3"></td>'.
            '<td class="differential-toc-meta">'.$meta.'</td>'.
          '</tr>';
      }
    }

    return
      '<div class="differential-toc differential-panel">'.
        '<h1>Table of Contents</h1>'.
        '<table>'.
          implode("\n", $rows).
        '</table>'.
      '</div>';
  }
}
