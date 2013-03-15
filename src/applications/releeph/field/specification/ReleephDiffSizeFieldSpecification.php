<?php

/**
 * While this class could take advantage of bulkLoad(), in practice
 * loadRelatives fixes all that for us.
 */
final class ReleephDiffSizeFieldSpecification
  extends ReleephFieldSpecification {

  const LINES_WEIGHT =  1;
  const PATHS_WEIGHT = 30;
  const MAX_POINTS = 1000;

  public function getName() {
    return 'Size';
  }

  public function renderValueForHeaderView() {
    $diff_rev = $this->getReleephRequest()->loadDifferentialRevision();
    if (!$diff_rev) {
      return '';
    }

    $diffs = $diff_rev->loadRelatives(
      new DifferentialDiff(),
      'revisionID',
      'getID',
      'creationMethod <> "commit"');

    $all_changesets = array();
    $most_recent_changesets = null;
    foreach ($diffs as $diff) {
      $changesets = $diff->loadRelatives(new DifferentialChangeset(), 'diffID');
      $all_changesets += $changesets;
      $most_recent_changesets = $changesets;
    }

    // The score is based on all changesets for all versions of this diff
    $all_changes = $this->countLinesAndPaths($all_changesets);
    $points =
      self::LINES_WEIGHT * $all_changes['code']['lines'] +
      self::PATHS_WEIGHT * count($all_changes['code']['paths']);

    // The blurb is just based on the most recent version of the diff
    $mr_changes = $this->countLinesAndPaths($most_recent_changesets);

    $test_tag = '';
    if ($mr_changes['tests']['paths']) {
      Javelin::initBehavior('phabricator-tooltips');
      require_celerity_resource('aphront-tooltip-css');

      $test_blurb =
        pht('%d line(s)', $mr_changes['tests']['lines']).' and '.
        pht('%d path(s)', count($mr_changes['tests']['paths'])).
        " contain changes to test code:\n";
      foreach ($mr_changes['tests']['paths'] as $mr_test_path) {
        $test_blurb .= pht("%s\n", $mr_test_path);
      }

      $test_tag = javelin_tag(
        'span',
        array(
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $test_blurb,
            'align' => 'E',
            'size' => 'auto'),
          'style' => ''),
        ' + tests');
    }

    $blurb = hsprintf("%s%s.",
      pht('%d line(s)', $mr_changes['code']['lines']).' and '.
      pht('%d path(s)', count($mr_changes['code']['paths'])).' over '.
      pht('%d diff(s)', count($diffs)),
      $test_tag);

    return id(new AphrontProgressBarView())
      ->setValue($points)
      ->setMax(self::MAX_POINTS)
      ->setCaption($blurb)
      ->render();
  }

  private function countLinesAndPaths(array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');
    $lines = 0;
    $paths_touched = array();
    $test_lines = 0;
    $test_paths_touched = array();

    foreach ($changesets as $ch) {
      if ($this->getReleephProject()->isTestFile($ch->getFilename())) {
        $test_lines += $ch->getAddLines() + $ch->getDelLines();
        $test_paths_touched[] = $ch->getFilename();
      } else {
        $lines += $ch->getAddLines() + $ch->getDelLines();
        $paths_touched[] = $ch->getFilename();
      }
    }
    return array(
      'code' => array(
        'lines' => $lines,
        'paths' => array_unique($paths_touched),
      ),
      'tests' => array(
        'lines' => $test_lines,
        'paths' => array_unique($test_paths_touched),
      )
    );
  }
}
