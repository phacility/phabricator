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

final class DifferentialLintFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Lint:';
  }

  public function getRequiredDiffProperties() {
    return array('arc:lint');
  }

  public function renderValueForRevisionView() {
    $diff = $this->getDiff();
    $path_changesets = mpull($diff->loadChangesets(), 'getId', 'getFilename');

    $lstar = DifferentialRevisionUpdateHistoryView::renderDiffLintStar($diff);
    $lmsg = DifferentialRevisionUpdateHistoryView::getDiffLintMessage($diff);
    $ldata = $this->getDiffProperty('arc:lint');
    $ltail = null;
    if ($ldata) {
      $ldata = igroup($ldata, 'path');
      $lint_messages = array();
      foreach ($ldata as $path => $messages) {
        $message_markup = array();
        foreach ($messages as $message) {
          $path = idx($message, 'path');
          $line = idx($message, 'line');

          $code = idx($message, 'code');
          $severity = idx($message, 'severity');

          $name = idx($message, 'name');
          $description = idx($message, 'description');

          $line_link = phutil_escape_html($line);
          if (isset($path_changesets[$path])) {
            // TODO: Create standalone links for large diffs. Logic is in
            // DifferentialDiffTableOfContentsView::renderChangesetLink().
            $line_link = phutil_render_tag(
              'a',
              array(
                'href' => '#C'.$path_changesets[$path].'NL'.$line,
              ),
              $line_link);
          }
          $message_markup[] = hsprintf(
            '<li>'.
              '<span class="lint-severity-%s">%s</span> (%s) %s '.
              'at line '.$line_link.
              '<p>%s</p>'.
            '</li>',
            $severity,
            ucwords($severity),
            $code,
            $name,
            $description);
        }
        $lint_messages[] =
          '<li class="lint-file-block">'.
            'Lint for <strong>'.phutil_escape_html($path).'</strong>'.
            '<ul>'.implode("\n", $message_markup).'</ul>'.
          '</li>';
      }
      $ltail =
        '<div class="differential-lint-block">'.
          '<ul>'.
            implode("\n", $lint_messages).
          '</ul>'.
        '</div>';
    }

    return $lstar.' '.$lmsg.$ltail;
  }
}
