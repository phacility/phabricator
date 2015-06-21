<?php

final class DifferentialLintField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:lint';
  }

  public function getFieldName() {
    return pht('Lint');
  }

  public function getFieldDescription() {
    return pht('Shows lint results.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    return null;
  }

  public function shouldAppearInDiffPropertyView() {
    return true;
  }

  public function renderDiffPropertyViewLabel(DifferentialDiff $diff) {
    return $this->getFieldName();
  }

  public function renderDiffPropertyViewValue(DifferentialDiff $diff) {
    // TODO: This load is slightly inefficient, but most of this is moving
    // to Harbormaster and this simplifies the transition. Eat 1-2 extra
    // queries for now.
    $keys = array(
      'arc:lint',
      'arc:lint-excuse',
    );

    $properties = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d AND name IN (%Ls)',
      $diff->getID(),
      $keys);
    $properties = mpull($properties, 'getData', 'getName');

    foreach ($keys as $key) {
      $diff->attachProperty($key, idx($properties, $key));
    }

    $status = $this->renderLintStatus($diff);

    $lint = array();

    // TODO: Look for Harbormaster messages here.

    if (!$lint) {
      // No Harbormaster messages, so look for legacy messages and make them
      // look like modern messages.
      $legacy_lint = $diff->getProperty('arc:lint');
      if ($legacy_lint) {
        // Show the top 100 legacy lint messages. Previously, we showed some
        // by default and let the user toggle the rest. With modern messages,
        // we can send the user to the Harbormaster detail page. Just show
        // "a lot" of messages in legacy cases to try to strike a balance
        // between implementation simplicitly and compatibility.
        $legacy_lint = array_slice($legacy_lint, 0, 100);

        $target = new HarbormasterBuildTarget();
        foreach ($legacy_lint as $message) {
          try {
            $modern = HarbormasterBuildLintMessage::newFromDictionary(
              $target,
              $this->getModernLintMessageDictionary($message));
            $lint[] = $modern;
          } catch (Exception $ex) {
            // Ignore any poorly formatted messages.
          }
        }
      }
    }

    if ($lint) {
      $path_map = mpull($diff->loadChangesets(), 'getID', 'getFilename');
      foreach ($path_map as $path => $id) {
        $href = '#C'.$id.'NL';

        // TODO: When the diff is not the right-hand-size diff, we should
        // ideally adjust this URI to be absolute.

        $path_map[$path] = $href;
      }

      $view = id(new HarbormasterLintPropertyView())
        ->setPathURIMap($path_map)
        ->setLintMessages($lint);
    } else {
      $view = null;
    }

    return array(
      $status,
      $view,
    );
  }

  public function getWarningsForDetailView() {
    $status = $this->getObject()->getActiveDiff()->getLintStatus();
    if ($status < DifferentialLintStatus::LINT_WARN) {
      return array();
    }
    if ($status == DifferentialLintStatus::LINT_AUTO_SKIP) {
      return array();
    }

    $warnings = array();
    if ($status == DifferentialLintStatus::LINT_SKIP) {
      $warnings[] = pht(
        'Lint was skipped when generating these changes.');
    } else if ($status == DifferentialLintStatus::LINT_POSTPONED) {
      $warnings[] = pht(
        'Background linting has not finished executing on these changes.');
    } else {
      $warnings[] = pht('These changes have lint problems.');
    }

    return $warnings;
  }

  private function renderLintStatus(DifferentialDiff $diff) {
    $colors = array(
      DifferentialLintStatus::LINT_NONE => 'grey',
      DifferentialLintStatus::LINT_OKAY => 'green',
      DifferentialLintStatus::LINT_WARN => 'yellow',
      DifferentialLintStatus::LINT_FAIL => 'red',
      DifferentialLintStatus::LINT_SKIP => 'blue',
      DifferentialLintStatus::LINT_AUTO_SKIP => 'blue',
      DifferentialLintStatus::LINT_POSTPONED => 'blue',
    );
    $icon_color = idx($colors, $diff->getLintStatus(), 'grey');

    $message = DifferentialRevisionUpdateHistoryView::getDiffLintMessage($diff);

    $excuse = $diff->getProperty('arc:lint-excuse');
    if (strlen($excuse)) {
      $excuse = array(
        phutil_tag('strong', array(), pht('Excuse:')),
        ' ',
        phutil_escape_html_newlines($excuse),
      );
    }

    $status = id(new PHUIStatusListView())
      ->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_STAR, $icon_color)
          ->setTarget($message)
          ->setNote($excuse));

    return $status;
  }

  private function getModernLintMessageDictionary(array $map) {
    // Strip out `null` values to satisfy stricter typechecks.
    foreach ($map as $key => $value) {
      if ($value === null) {
        unset($map[$key]);
      }
    }

    // TODO: We might need to remap some stuff here?
    return $map;
  }


}
