<?php

final class PHUIDiffTableOfContentsItemView extends AphrontView {

  private $changeset;
  private $isVisible = true;
  private $anchor;
  private $coverage;
  private $coverageID;
  private $context;
  private $packages;

  public function setChangeset(DifferentialChangeset $changeset) {
    $this->changeset = $changeset;
    return $this;
  }

  public function getChangeset() {
    return $this->changeset;
  }

  public function setIsVisible($is_visible) {
    $this->isVisible = $is_visible;
    return $this;
  }

  public function getIsVisible() {
    return $this->isVisible;
  }

  public function setAnchor($anchor) {
    $this->anchor = $anchor;
    return $this;
  }

  public function getAnchor() {
    return $this->anchor;
  }

  public function setCoverage($coverage) {
    $this->coverage = $coverage;
    return $this;
  }

  public function getCoverage() {
    return $this->coverage;
  }

  public function setCoverageID($coverage_id) {
    $this->coverageID = $coverage_id;
    return $this;
  }

  public function getCoverageID() {
    return $this->coverageID;
  }

  public function setContext($context) {
    $this->context = $context;
    return $this;
  }

  public function getContext() {
    return $this->context;
  }

  public function setPackages(array $packages) {
    assert_instances_of($packages, 'PhabricatorOwnersPackage');
    $this->packages = mpull($packages, null, 'getPHID');
    return $this;
  }

  public function getPackages() {
    return $this->packages;
  }

  public function render() {
    $changeset = $this->getChangeset();

    $cells = array();

    $cells[] = $this->getContext();

    $cells[] = $this->renderPathChangeCharacter();
    $cells[] = $this->renderPropertyChangeCharacter();
    $cells[] = $this->renderPropertyChangeDescription();

    $link = $this->renderChangesetLink();
    $lines = $this->renderChangesetLines();
    $meta = $this->renderChangesetMetadata();

    $cells[] = array(
      $link,
      $lines,
      $meta,
    );

    $cells[] = $this->renderCoverage();
    $cells[] = $this->renderModifiedCoverage();

    $cells[] = $this->renderPackages();

    return $cells;
  }

  private function renderPathChangeCharacter() {
    $changeset = $this->getChangeset();
    $type = $changeset->getChangeType();

    $color = DifferentialChangeType::getSummaryColorForChangeType($type);
    $char = DifferentialChangeType::getSummaryCharacterForChangeType($type);
    $title = DifferentialChangeType::getFullNameForChangeType($type);

    return javelin_tag(
      'span',
      array(
        'sigil' => 'has-tooltip',
        'meta' => array(
          'tip' => $title,
          'align' => 'E',
        ),
        'class' => 'phui-text-'.$color,
      ),
      $char);
  }

  private function renderPropertyChangeCharacter() {
    $changeset = $this->getChangeset();

    $old = $changeset->getOldProperties();
    $new = $changeset->getNewProperties();

    if ($old === $new) {
      return null;
    }

    return javelin_tag(
      'span',
      array(
        'sigil' => 'has-tooltip',
        'meta' => array(
          'tip' => pht('Properties Modified'),
          'align' => 'E',
          'size' => 200,
        ),
      ),
      'M');
  }

  private function renderPropertyChangeDescription() {
    $changeset = $this->getChangeset();

    $file_type = $changeset->getFileType();

    $desc = DifferentialChangeType::getShortNameForFileType($file_type);
    if ($desc === null) {
      return null;
    }

    return pht('(%s)', $desc);
  }

  private function renderChangesetLink() {
    $anchor = $this->getAnchor();

    $changeset = $this->getChangeset();
    $name = $changeset->getDisplayFilename();

    $change_type = $changeset->getChangeType();
    if (DifferentialChangeType::isOldLocationChangeType($change_type)) {
      $away = $changeset->getAwayPaths();
      if (count($away) == 1) {
        if ($change_type == DifferentialChangeType::TYPE_MOVE_AWAY) {
          $right_arrow = "\xE2\x86\x92";
          $name = $this->renderRename($name, head($away), $right_arrow);
        }
      }
    } else if ($change_type == DifferentialChangeType::TYPE_MOVE_HERE) {
      $left_arrow = "\xE2\x86\x90";
      $name = $this->renderRename($name, $changeset->getOldFile(), $left_arrow);
    }

    return javelin_tag(
      'a',
      array(
        'href' => '#'.$anchor,
        'sigil' => 'differential-load',
        'meta' => array(
          'id' => 'diff-'.$anchor,
        ),
      ),
      $name);
  }

  private function renderChangesetLines() {
    $changeset = $this->getChangeset();

    $line_count = $changeset->getAffectedLineCount();
    if (!$line_count) {
      return null;
    }

    return ' '.pht('(%d line(s))', $line_count);
  }

  private function renderCoverage() {
    $not_applicable = '-';

    $coverage = $this->getCoverage();
    if (!strlen($coverage)) {
      return $not_applicable;
    }

    $covered = substr_count($coverage, 'C');
    $not_covered = substr_count($coverage, 'U');

    if (!$not_covered && !$covered) {
      return $not_applicable;
    }

    return sprintf('%d%%', 100 * ($covered / ($covered + $not_covered)));
  }

  private function renderModifiedCoverage() {
    $not_applicable = '-';

    $coverage = $this->getCoverage();
    if (!strlen($coverage)) {
      return $not_applicable;
    }

    if ($this->getIsVisible()) {
      $label = pht('Loading...');
    } else {
      $label = pht('?');
    }

    return phutil_tag(
      'div',
      array(
        'id' => $this->getCoverageID(),
        'class' => 'differential-mcoverage-loading',
      ),
      $label);
  }

  private function renderChangesetMetadata() {
    $changeset = $this->getChangeset();
    $type = $changeset->getChangeType();

    $meta = array();
    if (DifferentialChangeType::isOldLocationChangeType($type)) {
      $away = $changeset->getAwayPaths();
      if (count($away) > 1) {
        if ($type == DifferentialChangeType::TYPE_MULTICOPY) {
          $meta[] = pht('Deleted after being copied to multiple locations:');
        } else {
          $meta[] = pht('Copied to multiple locations:');
        }
        foreach ($away as $path) {
          $meta[] = $path;
        }
      } else {
        if ($type == DifferentialChangeType::TYPE_MOVE_AWAY) {
          // This case is handled when we render the path.
        } else {
          $meta[] = pht('Copied to %s', head($away));
        }
      }
    } else if ($type == DifferentialChangeType::TYPE_COPY_HERE) {
      $meta[] = pht('Copied from %s', $changeset->getOldFile());
    }

    if (!$meta) {
      return null;
    }

    $meta = phutil_implode_html(phutil_tag('br'), $meta);

    return phutil_tag(
      'div',
      array(
        'class' => 'differential-toc-meta',
      ),
      $meta);
  }

  private function renderPackages() {
    $packages = $this->getPackages();
    if (!$packages) {
      return null;
    }

    $viewer = $this->getUser();
    $package_phids = mpull($packages, 'getPHID');

    return $viewer->renderHandleList($package_phids);
  }

  private function renderRename($self, $other, $arrow) {
    $old = explode('/', $self);
    $new = explode('/', $other);

    $start = count($old);
    foreach ($old as $index => $part) {
      if (!isset($new[$index]) || $part != $new[$index]) {
        $start = $index;
        break;
      }
    }

    $end = count($old);
    foreach (array_reverse($old) as $from_end => $part) {
      $index = count($new) - $from_end - 1;
      if (!isset($new[$index]) || $part != $new[$index]) {
        $end = $from_end;
        break;
      }
    }

    $rename =
      '{'.
      implode('/', array_slice($old, $start, count($old) - $end - $start)).
      ' '.$arrow.' '.
      implode('/', array_slice($new, $start, count($new) - $end - $start)).
      '}';

    array_splice($new, $start, count($new) - $end - $start, $rename);

    return implode('/', $new);
  }

}
