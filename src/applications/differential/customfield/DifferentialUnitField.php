<?php

final class DifferentialUnitField
  extends DifferentialHarbormasterField {

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

  protected function getLegacyProperty() {
    return 'arc:unit';
  }

  protected function getDiffPropertyKeys() {
    return array(
      'arc:unit',
      'arc:unit-excuse',
    );
  }

  protected function loadHarbormasterTargetMessages(array $target_phids) {
    return id(new HarbormasterBuildUnitMessage())->loadAllWhere(
      'buildTargetPHID IN (%Ls)',
      $target_phids);
  }

  protected function newModernMessage(array $message) {
    return HarbormasterBuildUnitMessage::newFromDictionary(
      new HarbormasterBuildTarget(),
      $this->getModernUnitMessageDictionary($message));
  }

  protected function newHarbormasterMessageView(array $messages) {
    foreach ($messages as $key => $message) {
      switch ($message->getResult()) {
        case ArcanistUnitTestResult::RESULT_PASS:
        case ArcanistUnitTestResult::RESULT_SKIP:
          // Don't show "Pass" or "Skip" in the UI since they aren't very
          // interesting. The user can click through to the full results if
          // they want details.
          unset($messages[$key]);
          break;
      }
    }

    if (!$messages) {
      return null;
    }

    return id(new HarbormasterUnitPropertyView())
      ->setLimit(10)
      ->setUnitMessages($messages);
  }

  public function getWarningsForDetailView() {
    $status = $this->getObject()->getActiveDiff()->getUnitStatus();

    $warnings = array();
    if ($status < DifferentialUnitStatus::UNIT_WARN) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_AUTO_SKIP) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_SKIP) {
      $warnings[] = pht(
        'Unit tests were skipped when generating these changes.');
    } else {
      $warnings[] = pht('These changes have unit test problems.');
    }

    return $warnings;
  }

  protected function renderHarbormasterStatus(
    DifferentialDiff $diff,
    array $messages) {

    $colors = array(
      DifferentialUnitStatus::UNIT_NONE => 'grey',
      DifferentialUnitStatus::UNIT_OKAY => 'green',
      DifferentialUnitStatus::UNIT_WARN => 'yellow',
      DifferentialUnitStatus::UNIT_FAIL => 'red',
      DifferentialUnitStatus::UNIT_SKIP => 'blue',
      DifferentialUnitStatus::UNIT_AUTO_SKIP => 'blue',
    );
    $icon_color = idx($colors, $diff->getUnitStatus(), 'grey');

    $message = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage($diff);

    $note = array();

    $groups = mgroup($messages, 'getResult');

    $groups = array_select_keys(
      $groups,
      array(
        ArcanistUnitTestResult::RESULT_FAIL,
        ArcanistUnitTestResult::RESULT_BROKEN,
        ArcanistUnitTestResult::RESULT_UNSOUND,
        ArcanistUnitTestResult::RESULT_SKIP,
        ArcanistUnitTestResult::RESULT_PASS,
      )) + $groups;

    foreach ($groups as $result => $group) {
      $count = new PhutilNumber(count($group));
      switch ($result) {
        case ArcanistUnitTestResult::RESULT_PASS:
          $note[] = pht('%s Passed Test(s)', $count);
          break;
        case ArcanistUnitTestResult::RESULT_FAIL:
          $note[] = pht('%s Failed Test(s)', $count);
          break;
        case ArcanistUnitTestResult::RESULT_SKIP:
          $note[] = pht('%s Skipped Test(s)', $count);
          break;
        case ArcanistUnitTestResult::RESULT_BROKEN:
          $note[] = pht('%s Broken Test(s)', $count);
          break;
        case ArcanistUnitTestResult::RESULT_UNSOUND:
          $note[] = pht('%s Unsound Test(s)', $count);
          break;
        default:
          $note[] = pht('%s Other Test(s)', $count);
          break;
      }
    }

    $buildable = $diff->getBuildable();
    if ($buildable) {
      $full_results = '/harbormaster/unit/'.$buildable->getID().'/';
      $note[] = phutil_tag(
        'a',
        array(
          'href' => $full_results,
        ),
        pht('View Full Results'));
    }

    $excuse = $diff->getProperty('arc:unit-excuse');
    if (strlen($excuse)) {
      $excuse = array(
        phutil_tag('strong', array(), pht('Excuse:')),
        ' ',
        phutil_escape_html_newlines($excuse),
      );
      $note[] = $excuse;
    }

    $note = phutil_implode_html(" \xC2\xB7 ", $note);

    $status = id(new PHUIStatusListView())
      ->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_STAR, $icon_color)
          ->setTarget($message)
          ->setNote($note));

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
