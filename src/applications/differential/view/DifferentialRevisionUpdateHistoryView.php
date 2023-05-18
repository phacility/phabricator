<?php

final class DifferentialRevisionUpdateHistoryView extends AphrontView {

  private $diffs = array();
  private $selectedVersusDiffID;
  private $selectedDiffID;
  private $commitsForLinks = array();
  private $unitStatus = array();

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

  public function setCommitsForLinks(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commitsForLinks = $commits;
    return $this;
  }

  public function setDiffUnitStatuses(array $unit_status) {
    $this->unitStatus = $unit_status;
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
        $lint = $this->newLintStatusView($diff);
        $unit = $this->newUnitStatusView($diff);
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
          'button',
          array(),
          pht('Show Diff')),
      ));

    $content = phabricator_form(
      $this->getUser(),
      array(
        'method' => 'GET',
        'action' => '/D'.$revision_id.'#toc',
      ),
      array(
        $table,
        $show_diff,
      ));

    return $content;
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

  private function newLintStatusView(DifferentialDiff $diff) {
    $value = $diff->getLintStatus();
    $status = DifferentialLintStatus::newStatusFromValue($value);

    $icon = $status->getIconIcon();
    $color = $status->getIconColor();
    $name = $status->getName();

    return $this->newDiffStatusIconView($icon, $color, $name);
  }

  private function newUnitStatusView(DifferentialDiff $diff) {
    $value = $diff->getUnitStatus();

    // NOTE: We may be overriding the value on the diff with a value from
    // Harbormaster.
    $value = idx($this->unitStatus, $diff->getPHID(), $value);

    $status = DifferentialUnitStatus::newStatusFromValue($value);

    $icon = $status->getIconIcon();
    $color = $status->getIconColor();
    $name = $status->getName();

    return $this->newDiffStatusIconView($icon, $color, $name);
  }

  private function newDiffStatusIconView($icon, $color, $name) {
    return id(new PHUIIconView())
      ->setIcon($icon, $color)
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => $name,
        ));
  }

}
