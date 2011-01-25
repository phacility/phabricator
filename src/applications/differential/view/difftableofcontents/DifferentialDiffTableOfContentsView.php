<?php

/*
 * Copyright 2011 Facebook, Inc.
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

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function render() {
    require_celerity_resource('differential-table-of-contents-css');

    $rows = array();

    $changesets = $this->changesets;
    foreach ($changesets as $changeset) {
      $file = $changeset->getFilename();
      $display_file = $changeset->getDisplayFilename();

      $type = $changeset->getChangeType();
      $ftype = $changeset->getFileType();

      if (DifferentialChangeType::isOldLocationChangeType($type)) {
        $link = phutil_escape_html($display_file);
        $away = $changeset->getAwayPaths();
        if (count($away) > 1) {
          $meta = array();
          if ($type == DifferentialChangeType::TYPE_MULTICOPY) {
            $meta[] = 'Deleted after being copied to multiple locations:';
          } else {
            $meta[] = 'Copied to multiple locations:';
          }
          foreach ($away as $path) {
            $meta[] = $path;
          }
          $meta = implode('<br />', $meta);
        } else {
          if ($type == DifferentialChangeType::TYPE_MOVE_AWAY) {
            $meta = 'Moved to '.reset($away);
          } else {
            $meta = 'Copied to '.reset($away);
          }
        }
      } else {
        $link = phutil_render_tag(
          'a',
          array(
            'href' => '#', // TODO: filename normalizer
          ),
          phutil_escape_html($display_file));
        if ($type == DifferentialChangeType::TYPE_MOVE_HERE) {
          $meta = 'Moved from '.phutil_escape_html($changeset->getOldFile());
        } else if ($type == DifferentialChangeType::TYPE_COPY_HERE) {
          $meta = 'Copied from '.phutil_escape_html($changeset->getOldFile());
        } else {
          $meta = null;
        }
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
          '<td class="differential-toc-char" title='.$chartitle.'>'.$char.'</td>'.
          '<td class="differential-toc-prop">'.$pchar.'</td>'.
          '<td class="differential-toc-ftype">'.$desc.'</td>'.
          '<td class="differential-toc-file">'.$link.$lines.'</td>'.
        '</tr>';
      if ($meta) {
        $rows[] =
          '<tr>'.
            '<td colspan="3" />'.
            '<td class="differential-toc-meta">'.$meta.'</td>'.
          '</tr>';
      }
    }

    return
      '<div class="differential-toc">'.
        '<h1>Table of Contents</h1>'.
        '<table>'.
          implode("\n", $rows).
        '</table>'.
      '</div>';
  }
}
