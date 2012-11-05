<?php

final class DifferentialRevisionUpdateHistoryView extends AphrontView {

  private $diffs = array();
  private $selectedVersusDiffID;
  private $selectedDiffID;
  private $selectedWhitespace;
  private $user;

  public function setDiffs(array $diffs) {
    assert_instances_of($diffs, 'DifferentialDiff');
    $this->diffs = $diffs;
    return $this;
  }

  public function setSelectedVersusDiffID($id) {
    $this->selectedVersusDiffID = $id;
    return $this;
  }

  public function setSelectedDiffID($id) {
    $this->selectedDiffID = $id;
    return $this;
  }

  public function setSelectedWhitespace($whitespace) {
    $this->selectedWhitespace = $whitespace;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function render() {

    require_celerity_resource('differential-core-view-css');
    require_celerity_resource('differential-revision-history-css');

    $data = array(
      array(
        'name' => 'Base',
        'id'   => null,
        'desc' => 'Base',
        'age'  => null,
        'obj'  => null,
      ),
    );

    $seq = 0;
    foreach ($this->diffs as $diff) {
      $data[] = array(
        'name' => 'Diff '.(++$seq),
        'id'   => $diff->getID(),
        'desc' => $diff->getDescription(),
        'age'  => $diff->getDateCreated(),
        'obj'  => $diff,
      );
    }

    $max_id = $diff->getID();

    $idx = 0;
    $rows = array();
    $disable = false;
    $radios = array();
    $last_base = null;
    foreach ($data as $row) {

      $diff = $row['obj'];
      $name = $row['name'];
      $id   = $row['id'];

      $old_class = null;
      $new_class = null;

      if ($id) {
        $new_checked = ($this->selectedDiffID == $id);
        $new = javelin_render_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'id',
            'value' => $id,
            'checked' => $new_checked ? 'checked' : null,
            'sigil' => 'differential-new-radio',
          ));
        if ($new_checked) {
          $new_class = " revhistory-new-now";
          $disable = true;
        }
      } else {
        $new = null;
      }

      if ($max_id != $id) {
        $uniq = celerity_generate_unique_node_id();
        $old_checked = ($this->selectedVersusDiffID == $id);
        $old = phutil_render_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'vs',
            'value' => $id,
            'id' => $uniq,
            'checked' => $old_checked ? 'checked' : null,
            'disabled' => $disable ? 'disabled' : null,
          ));
        $radios[] = $uniq;
        if ($old_checked) {
          $old_class = " revhistory-old-now";
        }
      } else {
        $old = null;
      }

      $desc = $row['desc'];

      if ($row['age']) {
        $age = phabricator_datetime($row['age'], $this->getUser());
      } else {
        $age = null;
      }

      if (++$idx % 2) {
        $class = ' class="alt"';
      } else {
        $class = null;
      }

      if ($diff) {
        $lint = self::renderDiffLintStar($row['obj']);
        $unit = self::renderDiffUnitStar($row['obj']);
        $lint_message = self::getDiffLintMessage($diff);
        $unit_message = self::getDiffUnitMessage($diff);
        $lint_title = ' title="'.phutil_escape_html($lint_message).'"';
        $unit_title = ' title="'.phutil_escape_html($unit_message).'"';
        $base = $this->renderBaseRevision($diff);
      } else {
        $lint = null;
        $unit = null;
        $lint_title = null;
        $unit_title = null;
        $base = null;
      }

      if ($last_base !== null && $base !== $last_base) {
        // TODO: Render some kind of notice about rebases.
      }
      $last_base = $base;

      $rows[] =
        '<tr'.$class.'>'.
          '<td class="revhistory-name">'.phutil_escape_html($name).'</td>'.
          '<td class="revhistory-id">'.phutil_escape_html($id).'</td>'.
          '<td class="revhistory-base">'.phutil_escape_html($base).'</td>'.
          '<td class="revhistory-desc">'.phutil_escape_html($desc).'</td>'.
          '<td class="revhistory-age">'.$age.'</td>'.
          '<td class="revhistory-star"'.$lint_title.'>'.$lint.'</td>'.
          '<td class="revhistory-star"'.$unit_title.'>'.$unit.'</td>'.
          '<td class="revhistory-old'.$old_class.'">'.$old.'</td>'.
          '<td class="revhistory-new'.$new_class.'">'.$new.'</td>'.
        '</tr>';
    }

    Javelin::initBehavior(
      'differential-diff-radios',
      array(
        'radios' => $radios,
      ));

    $options = array(
      DifferentialChangesetParser::WHITESPACE_IGNORE_FORCE => 'Ignore All',
      DifferentialChangesetParser::WHITESPACE_IGNORE_ALL => 'Ignore Most',
      DifferentialChangesetParser::WHITESPACE_IGNORE_TRAILING =>
        'Ignore Trailing',
      DifferentialChangesetParser::WHITESPACE_SHOW_ALL => 'Show All',
    );

    $select = '<select name="whitespace">';
    foreach ($options as $value => $label) {
      $select .= phutil_render_tag(
        'option',
        array(
          'value' => $value,
          'selected' => ($value == $this->selectedWhitespace)
          ? 'selected'
          : null,
        ),
        phutil_escape_html($label));
    }
    $select .= '</select>';

    return
      '<div class="differential-revision-history differential-panel">'.
        '<h1>Revision Update History</h1>'.
        '<form action="#toc">'.
          '<table class="differential-revision-history-table">'.
            '<tr>'.
              '<th>Diff</th>'.
              '<th>ID</th>'.
              '<th>Base</th>'.
              '<th>Description</th>'.
              '<th>Created</th>'.
              '<th>Lint</th>'.
              '<th>Unit</th>'.
            '</tr>'.
            implode("\n", $rows).
            '<tr>'.
              '<td colspan="8" class="diff-differ-submit">'.
                '<label>Whitespace Changes: '.$select.'</label>'.
                '<button>Show Diff</button>'.
              '</td>'.
            '</tr>'.
          '</table>'.
        '</form>'.
      '</div>';
  }

  const STAR_NONE = 'none';
  const STAR_OKAY = 'okay';
  const STAR_WARN = 'warn';
  const STAR_FAIL = 'fail';
  const STAR_SKIP = 'skip';

  public static function renderDiffLintStar(DifferentialDiff $diff) {
    static $map = array(
      DifferentialLintStatus::LINT_NONE => self::STAR_NONE,
      DifferentialLintStatus::LINT_OKAY => self::STAR_OKAY,
      DifferentialLintStatus::LINT_WARN => self::STAR_WARN,
      DifferentialLintStatus::LINT_FAIL => self::STAR_FAIL,
      DifferentialLintStatus::LINT_SKIP => self::STAR_SKIP,
      DifferentialLintStatus::LINT_POSTPONED => self::STAR_SKIP
    );

    $star = idx($map, $diff->getLintStatus(), self::STAR_FAIL);

    return self::renderDiffStar($star);
  }

  public static function renderDiffUnitStar(DifferentialDiff $diff) {
    static $map = array(
      DifferentialUnitStatus::UNIT_NONE => self::STAR_NONE,
      DifferentialUnitStatus::UNIT_OKAY => self::STAR_OKAY,
      DifferentialUnitStatus::UNIT_WARN => self::STAR_WARN,
      DifferentialUnitStatus::UNIT_FAIL => self::STAR_FAIL,
      DifferentialUnitStatus::UNIT_SKIP => self::STAR_SKIP,
      DifferentialUnitStatus::UNIT_POSTPONED => self::STAR_SKIP,
    );

    $star = idx($map, $diff->getUnitStatus(), self::STAR_FAIL);

    return self::renderDiffStar($star);
  }

  public static function getDiffLintMessage(DifferentialDiff $diff) {
    switch ($diff->getLintStatus()) {
      case DifferentialLintStatus::LINT_NONE:
        return 'No Linters Available';
      case DifferentialLintStatus::LINT_OKAY:
        return 'Lint OK';
      case DifferentialLintStatus::LINT_WARN:
        return 'Lint Warnings';
      case DifferentialLintStatus::LINT_FAIL:
        return 'Lint Errors';
      case DifferentialLintStatus::LINT_SKIP:
        return 'Lint Skipped';
      case DifferentialLintStatus::LINT_POSTPONED:
        return 'Lint Postponed';
    }
    return '???';
  }

  public static function getDiffUnitMessage(DifferentialDiff $diff) {
    switch ($diff->getUnitStatus()) {
      case DifferentialUnitStatus::UNIT_NONE:
        return 'No Unit Test Coverage';
      case DifferentialUnitStatus::UNIT_OKAY:
        return 'Unit Tests OK';
      case DifferentialUnitStatus::UNIT_WARN:
        return 'Unit Test Warnings';
      case DifferentialUnitStatus::UNIT_FAIL:
        return 'Unit Test Errors';
      case DifferentialUnitStatus::UNIT_SKIP:
        return 'Unit Tests Skipped';
      case DifferentialUnitStatus::UNIT_POSTPONED:
        return 'Unit Tests Postponed';
    }
    return '???';
  }

  private static function renderDiffStar($star) {
    $class = 'diff-star-'.$star;
    return
      '<span class="'.$class.'">'.
        "\xE2\x98\x85".
      '</span>';
  }

  private function renderBaseRevision(DifferentialDiff $diff) {
    switch ($diff->getSourceControlSystem()) {
      case 'git':
        $base = $diff->getSourceControlBaseRevision();
        if (strpos($base, '@') === false) {
          return substr($base, 0, 7);
        } else {
          // The diff is from git-svn
          $base = explode('@', $base);
          $base = last($base);
          return $base;
        }
      case 'svn':
        $base = $diff->getSourceControlBaseRevision();
        $base = explode('@', $base);
        $base = last($base);
        return $base;
      default:
        return null;
    }
  }
}
