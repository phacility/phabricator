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

    $cells[] = $changeset->newFileTreeIcon();

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

  public function newLink() {
    $anchor = $this->getAnchor();

    $changeset = $this->getChangeset();
    $name = $changeset->getDisplayFilename();
    $name = basename($name);

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

  public function renderChangesetLines() {
    $changeset = $this->getChangeset();

    if ($changeset->getIsLowImportanceChangeset()) {
      return null;
    }

    $line_count = $changeset->getAffectedLineCount();
    if (!$line_count) {
      return null;
    }

    return pht('%d line(s)', $line_count);
  }

  public function renderCoverage() {
    $not_applicable = '-';

    $coverage = $this->getCoverage();
    if ($coverage === null || !strlen($coverage)) {
      return $not_applicable;
    }

    $covered = substr_count($coverage, 'C');
    $not_covered = substr_count($coverage, 'U');

    if (!$not_covered && !$covered) {
      return $not_applicable;
    }

    return sprintf('%d%%', 100 * ($covered / ($covered + $not_covered)));
  }

  public function renderModifiedCoverage() {
    $not_applicable = '-';

    $coverage = $this->getCoverage();
    if ($coverage === null || !strlen($coverage)) {
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

  public function renderPackages() {
    $packages = $this->getPackages();

    if (!$packages) {
      return null;
    }

    $viewer = $this->getViewer();
    $package_phids = mpull($packages, 'getPHID');

    return $viewer->renderHandleList($package_phids)
      ->setGlyphLimit(48);
  }

}
