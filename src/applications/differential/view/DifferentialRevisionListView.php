<?php

/**
 * Render a table of Differential revisions.
 */
final class DifferentialRevisionListView extends AphrontView {

  private $revisions;
  private $handles;
  private $highlightAge;
  private $header;
  private $noDataString;
  private $noBox;

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

  public function setHighlightAge($bool) {
    $this->highlightAge = $bool;
    return $this;
  }

  public function setNoBox($box) {
    $this->noBox = $box;
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
    $user = $this->user;
    if (!$user) {
      throw new PhutilInvalidStateException('setUser');
    }

    $fresh = PhabricatorEnv::getEnvConfig('differential.days-fresh');
    if ($fresh) {
      $fresh = PhabricatorCalendarHoliday::getNthBusinessDay(
        time(),
        -$fresh);
    }

    $stale = PhabricatorEnv::getEnvConfig('differential.days-stale');
    if ($stale) {
      $stale = PhabricatorCalendarHoliday::getNthBusinessDay(
        time(),
        -$stale);
    }

    $this->initBehavior('phabricator-tooltips', array());
    $this->requireResource('aphront-tooltip-css');

    $list = new PHUIObjectItemListView();

    foreach ($this->revisions as $revision) {
      $item = id(new PHUIObjectItemView())
        ->setUser($user);

      $icons = array();

      $phid = $revision->getPHID();
      $flag = $revision->getFlag($user);
      if ($flag) {
        $flag_class = PhabricatorFlagColor::getCSSClass($flag->getColor());
        $icons['flag'] = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-flag-icon '.$flag_class,
          ),
          '');
      }

      if ($revision->getDrafts($user)) {
        $icons['draft'] = true;
      }

      $modified = $revision->getDateModified();

      $status = $revision->getStatus();
      $show_age = ($fresh || $stale) &&
                  $this->highlightAge &&
                  !$revision->isClosed();

      if ($stale && $modified < $stale) {
        $object_age = PHUIObjectItemView::AGE_OLD;
      } else if ($fresh && $modified < $fresh) {
        $object_age = PHUIObjectItemView::AGE_STALE;
      } else {
        $object_age = PHUIObjectItemView::AGE_FRESH;
      }

      $status_name =
        ArcanistDifferentialRevisionStatus::getNameForRevisionStatus($status);

      if (isset($icons['flag'])) {
        $item->addHeadIcon($icons['flag']);
      }

      $item->setObjectName('D'.$revision->getID());
      $item->setHeader($revision->getTitle());
      $item->setHref('/D'.$revision->getID());

      if (isset($icons['draft'])) {
        $draft = id(new PHUIIconView())
          ->setIconFont('fa-comment yellow')
          ->addSigil('has-tooltip')
          ->setMetadata(
            array(
              'tip' => pht('Unsubmitted Comments'),
            ));
        $item->addAttribute($draft);
      }

      /* Most things 'Need Review', so accept it's the default */
      if ($status != ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
        $item->addAttribute($status_name);
      }

      // Author
      $author_handle = $this->handles[$revision->getAuthorPHID()];
      $item->addByline(pht('Author: %s', $author_handle->renderLink()));

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
      $item->setEpoch($revision->getDateModified(), $object_age);

      switch ($status) {
        case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
          break;
        case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
        case ArcanistDifferentialRevisionStatus::CHANGES_PLANNED:
          $item->setBarColor('red');
          break;
        case ArcanistDifferentialRevisionStatus::ACCEPTED:
          $item->setBarColor('green');
          break;
        case ArcanistDifferentialRevisionStatus::CLOSED:
          $item->setDisabled(true);
          break;
        case ArcanistDifferentialRevisionStatus::ABANDONED:
          $item->setBarColor('black');
          break;
      }

      $list->addItem($item);
    }

    $list->setNoDataString($this->noDataString);


    if ($this->header && !$this->noBox) {
      $list->setFlush(true);
      $list = id(new PHUIObjectBoxView())
        ->appendChild($list);

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
