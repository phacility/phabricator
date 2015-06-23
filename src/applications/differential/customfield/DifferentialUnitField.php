<?php

final class DifferentialUnitField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:unit';
  }

  public function getFieldName() {
    return pht('Unit');
  }

  public function getFieldDescription() {
    return pht('Shows unit test results.');
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
    // TODO: See DifferentialLintField.
    $keys = array(
      'arc:unit',
      'arc:unit-excuse',
    );

    $properties = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d AND name IN (%Ls)',
      $diff->getID(),
      $keys);
    $properties = mpull($properties, 'getData', 'getName');

    foreach ($keys as $key) {
      $diff->attachProperty($key, idx($properties, $key));
    }

    $status = $this->renderUnitStatus($diff);

    $unit = array();

    $buildable = $diff->getBuildable();
    if ($buildable) {
      $target_phids = array();
      foreach ($buildable->getBuilds() as $build) {
        foreach ($build->getBuildTargets() as $target) {
          $target_phids[] = $target->getPHID();
        }
      }

      $unit = id(new HarbormasterBuildUnitMessage())->loadAllWhere(
        'buildTargetPHID IN (%Ls) LIMIT 25',
        $target_phids);
    }

    if (!$unit) {
      $legacy_unit = $diff->getProperty('arc:unit');
      if ($legacy_unit) {
        // Show the top 100 legacy unit messages.
        $legacy_unit = array_slice($legacy_unit, 0, 100);

        $target = new HarbormasterBuildTarget();
        foreach ($legacy_unit as $message) {
          try {
            $modern = HarbormasterBuildUnitMessage::newFromDictionary(
              $target,
              $this->getModernUnitMessageDictionary($message));
            $unit[] = $modern;
          } catch (Exception $ex) {
            // Just ignore it if legacy messages aren't formatted like
            // we expect.
          }
        }
      }
    }

    if ($unit) {
      $path_map = mpull($diff->loadChangesets(), 'getID', 'getFilename');
      foreach ($path_map as $path => $id) {
        $href = '#C'.$id.'NL';

        // TODO: When the diff is not the right-hand-size diff, we should
        // ideally adjust this URI to be absolute.

        $path_map[$path] = $href;
      }

      $view = id(new HarbormasterUnitPropertyView())
        ->setPathURIMap($path_map)
        ->setUnitMessages($unit);
    } else {
      $view = null;
    }

    return array(
      $status,
      $view,
    );
  }

  public function getWarningsForDetailView() {
    $status = $this->getObject()->getActiveDiff()->getUnitStatus();

    $warnings = array();
    if ($status < DifferentialUnitStatus::UNIT_WARN) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_AUTO_SKIP) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_POSTPONED) {
      $warnings[] = pht(
        'Background tests have not finished executing on these changes.');
    } else if ($status == DifferentialUnitStatus::UNIT_SKIP) {
      $warnings[] = pht(
        'Unit tests were skipped when generating these changes.');
    } else {
      $warnings[] = pht('These changes have unit test problems.');
    }

    return $warnings;
  }


  private function renderUnitStatus(DifferentialDiff $diff) {
    $colors = array(
      DifferentialUnitStatus::UNIT_NONE => 'grey',
      DifferentialUnitStatus::UNIT_OKAY => 'green',
      DifferentialUnitStatus::UNIT_WARN => 'yellow',
      DifferentialUnitStatus::UNIT_FAIL => 'red',
      DifferentialUnitStatus::UNIT_SKIP => 'blue',
      DifferentialUnitStatus::UNIT_AUTO_SKIP => 'blue',
      DifferentialUnitStatus::UNIT_POSTPONED => 'blue',
    );
    $icon_color = idx($colors, $diff->getUnitStatus(), 'grey');

    $message = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage($diff);

    $excuse = $diff->getProperty('arc:unit-excuse');
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

  private function getModernUnitMessageDictionary(array $map) {
    // Strip out `null` values to satisfy stricter typechecks.
    foreach ($map as $key => $value) {
      if ($value === null) {
        unset($map[$key]);
      }
    }

    // TODO: Remap more stuff here?

    return $map;
  }


}
