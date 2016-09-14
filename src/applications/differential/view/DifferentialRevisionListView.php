<?php

/**
 * Render a table of Differential revisions.
 */
final class DifferentialRevisionListView extends AphrontView {

  private $revisions;
  private $handles;
  private $header;
  private $noDataString;
  private $noBox;
  private $background = null;
  private $unlandedDependencies = array();

  public function setUnlandedDependencies(array $unlanded_dependencies) {
    $this->unlandedDependencies = $unlanded_dependencies;
    return $this;
  }

  public function getUnlandedDependencies() {
    return $this->unlandedDependencies;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setRevisions(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    $this->revisions = $revisions;
    return $this;
  }

  public function setNoBox($box) {
    $this->noBox = $box;
    return $this;
  }

  public function setBackground($background) {
    $this->background = $background;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->revisions as $revision) {
      $phids[] = array($revision->getAuthorPHID());

      // TODO: Switch to getReviewerStatus(), but not all callers pass us
      // revisions with this data loaded.
      $phids[] = $revision->getReviewers();
    }
    return array_mergev($phids);
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    $viewer = $this->getViewer();

    $this->initBehavior('phabricator-tooltips', array());
    $this->requireResource('aphront-tooltip-css');

    $list = new PHUIObjectItemListView();

    foreach ($this->revisions as $revision) {
      $item = id(new PHUIObjectItemView())
        ->setUser($viewer);

      $icons = array();

      $phid = $revision->getPHID();
      $flag = $revision->getFlag($viewer);
      if ($flag) {
        $flag_class = PhabricatorFlagColor::getCSSClass($flag->getColor());
        $icons['flag'] = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-flag-icon '.$flag_class,
          ),
          '');
      }

      if ($revision->getDrafts($viewer)) {
        $icons['draft'] = true;
      }

      $modified = $revision->getDateModified();

      if (isset($icons['flag'])) {
        $item->addHeadIcon($icons['flag']);
      }

      $item->setObjectName('D'.$revision->getID());
      $item->setHeader($revision->getTitle());
      $item->setHref('/D'.$revision->getID());

      if (isset($icons['draft'])) {
        $draft = id(new PHUIIconView())
          ->setIcon('fa-comment yellow')
          ->addSigil('has-tooltip')
          ->setMetadata(
            array(
              'tip' => pht('Unsubmitted Comments'),
            ));
        $item->addAttribute($draft);
      }

      // Author
      $author_handle = $this->handles[$revision->getAuthorPHID()];
      $item->addByline(pht('Author: %s', $author_handle->renderLink()));

      $unlanded = idx($this->unlandedDependencies, $phid);
      if ($unlanded) {
        $item->addAttribute(
          array(
            id(new PHUIIconView())->setIcon('fa-chain-broken', 'red'),
            ' ',
            pht('Open Dependencies'),
          ));
      }

      $reviewers = array();
      // TODO: As above, this should be based on `getReviewerStatus()`.
      foreach ($revision->getReviewers() as $reviewer) {
        $reviewers[] = $this->handles[$reviewer]->renderLink();
      }
      if (!$reviewers) {
        $reviewers = phutil_tag('em', array(), pht('None'));
      } else {
        $reviewers = phutil_implode_html(', ', $reviewers);
      }

      $item->addAttribute(pht('Reviewers: %s', $reviewers));
      $item->setEpoch($revision->getDateModified());

      if ($revision->isClosed()) {
        $item->setDisabled(true);
      }

      $item->setStatusIcon(
        $revision->getStatusIcon(),
        $revision->getStatusDisplayName());

      $list->addItem($item);
    }

    $list->setNoDataString($this->noDataString);


    if ($this->header && !$this->noBox) {
      $list->setFlush(true);
      $list = id(new PHUIObjectBoxView())
        ->setBackground($this->background)
        ->setObjectList($list);

      if ($this->header instanceof PHUIHeaderView) {
        $list->setHeader($this->header);
      } else {
        $list->setHeaderText($this->header);
      }
    } else {
      $list->setHeader($this->header);
    }

    return $list;
  }

}
