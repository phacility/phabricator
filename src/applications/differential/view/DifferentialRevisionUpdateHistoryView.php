<?php

final class DifferentialRevisionUpdateHistoryView extends AphrontView {

  private $diffs = array();
  private $selectedVersusDiffID;
  private $selectedDiffID;
  private $selectedWhitespace;
  private $commitsForLinks = array();

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

  public function setCommitsForLinks(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commitsForLinks = $commits;
    return $this;
  }

  public function render() {
    $this->requireResource('differential-core-view-css');
    $this->requireResource('differential-revision-history-css');

    $data = array(
      array(
        'name' => pht('Base'),
        'id'   => null,
        'desc' => pht('Base'),
        'age'  => null,
        'obj'  => null,
      ),
    );

    $seq = 0;
    foreach ($this->diffs as $diff) {
      $data[] = array(
        'name' => pht('Diff %d', ++$seq),
        'id'   => $diff->getID(),
        'desc' => $diff->getDescription(),
        'age'  => $diff->getDateCreated(),
        'obj'  => $diff,
      );
    }

    $max_id = $diff->getID();
    $revision_id = $diff->getRevisionID();

    $idx = 0;
    $rows = array();
    $disable = false;
    $radios = array();
    $last_base = null;
    $rowc = array();
    foreach ($data as $row) {

      $diff = $row['obj'];
      $name = $row['name'];
      $id   = $row['id'];

      $old_class = false;
      $new_class = false;

      if ($id) {
        $new_checked = ($this->selectedDiffID == $id);
        $new = javelin_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'id',
            'value' => $id,
            'checked' => $new_checked ? 'checked' : null,
            'sigil' => 'differential-new-radio',
          ));
        if ($new_checked) {
          $new_class = true;
          $disable = true;
        }
        $new = phutil_tag(
          'div',
          array(
            'class' => 'differential-update-history-radio',
          ),
          $new);
      } else {
        $new = null;
      }

      if ($max_id != $id) {
        $uniq = celerity_generate_unique_node_id();
        $old_checked = ($this->selectedVersusDiffID == $id);
        $old = phutil_tag(
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
          $old_class = true;
        }
        $old = phutil_tag(
          'div',
          array(
            'class' => 'differential-update-history-radio',
          ),
          $old);
      } else {
        $old = null;
      }

      $desc = $row['desc'];

      if ($row['age']) {
        $age = phabricator_datetime($row['age'], $this->getUser());
      } else {
        $age = null;
      }

      if ($diff) {
        $lint = self::renderDiffLintStar($row['obj']);
        $lint = phutil_tag(
          'div',
          array(
            'class' => 'lintunit-star',
            'title' => self::getDiffLintMessage($diff),
          ),
          $lint);

        $unit = self::renderDiffUnitStar($row['obj']);
        $unit = phutil_tag(
          'div',
          array(
            'class' => 'lintunit-star',
            'title' => self::getDiffUnitMessage($diff),
          ),
          $unit);

        $base = $this->renderBaseRevision($diff);
      } else {
        $lint = null;
        $unit = null;
        $base = null;
      }

      if ($last_base !== null && $base !== $last_base) {
        // TODO: Render some kind of notice about rebases.
      }
      $last_base = $base;

      if ($revision_id) {
        $id_link = phutil_tag(
          'a',
          array(
            'href' => '/D'.$revision_id.'?id='.$id,
          ),
          $id);
      } else {
        $id_link = phutil_tag(
          'a',
          array(
            'href' => '/differential/diff/'.$id.'/',
          ),
          $id);
      }

      $rows[] = array(
        $name,
        $id_link,
        $base,
        $desc,
        $age,
        $lint,
        $unit,
        $old,
        $new,
      );

      $classes = array();
      if ($old_class) {
        $classes[] = 'differential-update-history-old-now';
      }
      if ($new_class) {
        $classes[] = 'differential-update-history-new-now';
      }
      $rowc[] = nonempty(implode(' ', $classes), null);
    }

    Javelin::initBehavior(
      'differential-diff-radios',
      array(
        'radios' => $radios,
      ));

    $options = array(
      DifferentialChangesetParser::WHITESPACE_IGNORE_ALL => pht('Ignore All'),
      DifferentialChangesetParser::WHITESPACE_IGNORE_MOST => pht('Ignore Most'),
      DifferentialChangesetParser::WHITESPACE_IGNORE_TRAILING =>
        pht('Ignore Trailing'),
      DifferentialChangesetParser::WHITESPACE_SHOW_ALL => pht('Show All'),
    );

    foreach ($options as $value => $label) {
      $options[$value] = phutil_tag(
        'option',
        array(
          'value' => $value,
          'selected' => ($value == $this->selectedWhitespace)
          ? 'selected'
          : null,
        ),
        $label);
    }
    $select = phutil_tag('select', array('name' => 'whitespace'), $options);


    $table = id(new AphrontTableView($rows));
    $table->setHeaders(
      array(
        pht('Diff'),
        pht('ID'),
        pht('Base'),
        pht('Description'),
        pht('Created'),
        pht('Lint'),
        pht('Unit'),
        '',
        '',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        '',
        '',
        'wide',
        'date',
        'center',
        'center',
        'center differential-update-history-old',
        'center differential-update-history-new',
      ));
    $table->setRowClasses($rowc);
    $table->setDeviceVisibility(
      array(
        true,
        true,
        false,
        true,
        false,
        false,
        false,
        true,
        true,
      ));

    $show_diff = phutil_tag(
      'div',
      array(
        'class' => 'differential-update-history-footer',
      ),
      array(
        phutil_tag(
          'label',
          array(),
          array(
            pht('Whitespace Changes:'),
            $select,
          )),
        phutil_tag(
          'button',
          array(),
          pht('Show Diff')),
      ));

    $content = phabricator_form(
      $this->getUser(),
      array(
        'action' => '#toc',
      ),
      array(
        $table,
        $show_diff,
      ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Revision Update History'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($content);
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
      DifferentialLintStatus::LINT_AUTO_SKIP => self::STAR_SKIP,
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
      DifferentialUnitStatus::UNIT_AUTO_SKIP => self::STAR_SKIP,
    );

    $star = idx($map, $diff->getUnitStatus(), self::STAR_FAIL);

    return self::renderDiffStar($star);
  }

  public static function getDiffLintMessage(DifferentialDiff $diff) {
    switch ($diff->getLintStatus()) {
      case DifferentialLintStatus::LINT_NONE:
        return pht('No Linters Available');
      case DifferentialLintStatus::LINT_OKAY:
        return pht('Lint OK');
      case DifferentialLintStatus::LINT_WARN:
        return pht('Lint Warnings');
      case DifferentialLintStatus::LINT_FAIL:
        return pht('Lint Errors');
      case DifferentialLintStatus::LINT_SKIP:
        return pht('Lint Skipped');
      case DifferentialLintStatus::LINT_AUTO_SKIP:
        return pht('Automatic diff as part of commit; lint not applicable.');
    }
    return pht('Unknown');
  }

  public static function getDiffUnitMessage(DifferentialDiff $diff) {
    switch ($diff->getUnitStatus()) {
      case DifferentialUnitStatus::UNIT_NONE:
        return pht('No Unit Test Coverage');
      case DifferentialUnitStatus::UNIT_OKAY:
        return pht('Unit Tests OK');
      case DifferentialUnitStatus::UNIT_WARN:
        return pht('Unit Test Warnings');
      case DifferentialUnitStatus::UNIT_FAIL:
        return pht('Unit Test Errors');
      case DifferentialUnitStatus::UNIT_SKIP:
        return pht('Unit Tests Skipped');
      case DifferentialUnitStatus::UNIT_AUTO_SKIP:
        return pht(
          'Automatic diff as part of commit; unit tests not applicable.');
    }
    return pht('Unknown');
  }

  private static function renderDiffStar($star) {
    $class = 'diff-star-'.$star;
    return phutil_tag(
      'span',
      array('class' => $class),
      "\xE2\x98\x85");
  }

  private function renderBaseRevision(DifferentialDiff $diff) {
    switch ($diff->getSourceControlSystem()) {
      case 'git':
        $base = $diff->getSourceControlBaseRevision();
        if (strpos($base, '@') === false) {
          $label = substr($base, 0, 7);
        } else {
          // The diff is from git-svn
          $base = explode('@', $base);
          $base = last($base);
          $label = $base;
        }
        break;
      case 'svn':
        $base = $diff->getSourceControlBaseRevision();
        $base = explode('@', $base);
        $base = last($base);
        $label = $base;
        break;
      default:
        $label = null;
        break;
    }
    $link = null;
    if ($label) {
      $commit_for_link = idx(
        $this->commitsForLinks,
        $diff->getSourceControlBaseRevision());
      if ($commit_for_link) {
        $link = phutil_tag(
          'a',
          array('href' => $commit_for_link->getURI()),
          $label);
      } else {
        $link = $label;
      }
    }
    return $link;
  }
}
