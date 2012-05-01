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
    return array('arc:lint', 'arc:lint-excuse');
  }

  private function getLintExcuse() {
    return $this->getDiffProperty('arc:lint-excuse');
  }

  public function renderValueForRevisionView() {
    $diff = $this->getDiff();
    $path_changesets = mpull($diff->loadChangesets(), 'getID', 'getFilename');

    $lstar = DifferentialRevisionUpdateHistoryView::renderDiffLintStar($diff);
    $lmsg = DifferentialRevisionUpdateHistoryView::getDiffLintMessage($diff);
    $ldata = $this->getDiffProperty('arc:lint');
    $ltail = null;

    $rows = array();

    $rows[] = array(
      'style'     => 'star',
      'name'      => $lstar,
      'value'     => $lmsg,
      'show'      => true,
    );

    $excuse = $this->getLintExcuse();
    if ($excuse) {
      $rows[] = array(
        'style'   => 'excuse',
        'name'    => 'Excuse',
        'value'   => nl2br(phutil_escape_html($excuse)),
        'show'    => true,
      );
    }

    $show_limit = 10;
    $hidden = array();

    if ($ldata) {
      $ldata = igroup($ldata, 'path');
      foreach ($ldata as $path => $messages) {

        $rows[] = array(
          'style' => 'section',
          'name'  => phutil_escape_html($path),
          'show'  => $show_limit,
        );

        foreach ($messages as $message) {
          $path = idx($message, 'path');
          $line = idx($message, 'line');

          $code = idx($message, 'code');
          $severity = idx($message, 'severity');

          $name = idx($message, 'name');
          $description = idx($message, 'description');

          $line_link = 'line '.phutil_escape_html($line);
          if (isset($path_changesets[$path])) {
            // TODO: Load very large diff before linking to line.
            $line_link = phutil_render_tag(
              'a',
              array(
                'href' => '#C'.$path_changesets[$path].'NL'.$line,
              ),
              $line_link);
          }

          if ($show_limit) {
            --$show_limit;
            $show = true;
          } else {
            $show = false;
            if (empty($hidden[$severity])) {
              $hidden[$severity] = 0;
            }
            $hidden[$severity]++;
          }

          $rows[] = array(
            'style' => $this->getSeverityStyle($severity),
            'name'  => phutil_escape_html(ucwords($severity)),
            'value' => hsprintf(
              "(%s) %s at {$line_link}",
              $code,
              $name),
            'show'  => $show,
          );

          if (strlen($description)) {
            $rows[] = array(
              'style' => 'details',
              'value' => nl2br(phutil_escape_html($description)),
              'show'  => false,
            );
            if (empty($hidden['details'])) {
              $hidden['details'] = 0;
            }
            $hidden['details']++;
          }
        }
      }
    }

    $show_string = $this->renderShowString($hidden);

    $view = new DifferentialResultsTableView();
    $view->setRows($rows);
    $view->setShowMoreString($show_string);

    return $view->render();
  }

  private function getSeverityStyle($severity) {
    $map = array(
      ArcanistLintSeverity::SEVERITY_ERROR      => 'red',
      ArcanistLintSeverity::SEVERITY_WARNING    => 'yellow',
      ArcanistLintSeverity::SEVERITY_AUTOFIX    => 'yellow',
      ArcanistLintSeverity::SEVERITY_ADVICE     => 'yellow',
    );
    return idx($map, $severity);
  }

  private function renderShowString(array $hidden) {
    if (!$hidden) {
      return null;
    }

    // Reorder hidden things by severity.
    $hidden = array_select_keys(
      $hidden,
      array(
        ArcanistLintSeverity::SEVERITY_ERROR,
        ArcanistLintSeverity::SEVERITY_WARNING,
        ArcanistLintSeverity::SEVERITY_AUTOFIX,
        ArcanistLintSeverity::SEVERITY_ADVICE,
        'details',
      )) + $hidden;

    $singular = array(
      ArcanistLintSeverity::SEVERITY_ERROR    => 'Error',
      ArcanistLintSeverity::SEVERITY_WARNING  => 'Warning',
      ArcanistLintSeverity::SEVERITY_AUTOFIX  => 'Auto-Fix',
      ArcanistLintSeverity::SEVERITY_ADVICE   => 'Advice',
      'details'                               => 'Detail',
    );

    $plural = array(
      ArcanistLintSeverity::SEVERITY_ERROR    => 'Errors',
      ArcanistLintSeverity::SEVERITY_WARNING  => 'Warnings',
      ArcanistLintSeverity::SEVERITY_AUTOFIX  => 'Auto-Fixes',
      ArcanistLintSeverity::SEVERITY_ADVICE   => 'Advice',
      'details'                               => 'Details',
    );

    $show = array();
    foreach ($hidden as $key => $value) {
      if ($value == 1) {
        $show[] = $value.' '.idx($singular, $key);
      } else {
        $show[] = $value.' '.idx($plural, $key);
      }
    }

    return "Show Full Lint Results (".implode(', ', $show).")";
  }

  public function renderWarningBoxForRevisionAccept() {
    $diff = $this->getDiff();
    $lint_warning = null;
    if ($diff->getLintStatus() >= DifferentialLintStatus::LINT_WARN) {
      $titles =
        array(
          DifferentialLintStatus::LINT_WARN => 'Lint Warning',
          DifferentialLintStatus::LINT_FAIL => 'Lint Failure',
          DifferentialLintStatus::LINT_SKIP => 'Lint Skipped'
        );
      if ($diff->getLintStatus() == DifferentialLintStatus::LINT_SKIP) {
        $content =
          "<p>This diff was created without running lint. Make sure you are ".
          "OK with that before you accept this diff.</p>";
      } else {
        $content =
          "<p>This diff has Lint Problems. Make sure you are OK with them ".
          "before you accept this diff.</p>";
      }
      $lint_warning = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_ERROR)
        ->setWidth(AphrontErrorView::WIDTH_WIDE)
        ->appendChild($content)
        ->setTitle(idx($titles, $diff->getLintStatus(), 'Warning'));
    }
    return $lint_warning;
  }

}
