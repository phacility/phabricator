<?php

final class DifferentialReviewersView extends AphrontView {

  private $reviewers;
  private $handles;
  private $diff;

  public function setReviewers(array $reviewers) {
    assert_instances_of($reviewers, 'DifferentialReviewer');
    $this->reviewers = $reviewers;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setActiveDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function render() {
    $viewer = $this->getUser();

    $view = new PHUIStatusListView();
    foreach ($this->reviewers as $reviewer) {
      $phid = $reviewer->getReviewerPHID();
      $handle = $this->handles[$phid];

      // If we're missing either the diff or action information for the
      // reviewer, render information as current.
      $is_current = (!$this->diff) ||
                    (!$reviewer->getDiffID()) ||
                    ($this->diff->getID() == $reviewer->getDiffID());

      $item = new PHUIStatusItemView();

      $item->setHighlighted($reviewer->hasAuthority($viewer));

      switch ($reviewer->getStatus()) {
        case DifferentialReviewerStatus::STATUS_ADDED:
          $item->setIcon(
            PHUIStatusItemView::ICON_OPEN,
            'bluegrey',
            pht('Review Requested'));
          break;

        case DifferentialReviewerStatus::STATUS_ACCEPTED:
          if ($is_current) {
            $item->setIcon(
              PHUIStatusItemView::ICON_ACCEPT,
              'green',
              pht('Accepted'));
          } else {
            $item->setIcon(
              PHUIStatusItemView::ICON_ACCEPT,
              'dark',
              pht('Accepted Prior Diff'));
          }
          break;

        case DifferentialReviewerStatus::STATUS_ACCEPTED_OLDER:
          $item->setIcon(
            PHUIStatusItemView::ICON_ACCEPT,
            'dark',
            pht('Accepted Prior Diff'));
          break;

        case DifferentialReviewerStatus::STATUS_REJECTED:
          if ($is_current) {
            $item->setIcon(
              PHUIStatusItemView::ICON_REJECT,
              'red',
              pht('Requested Changes'));
          } else {
            $item->setIcon(
              PHUIStatusItemView::ICON_REJECT,
              'dark',
              pht('Requested Changes to Prior Diff'));
          }
          break;

        case DifferentialReviewerStatus::STATUS_REJECTED_OLDER:
          $item->setIcon(
            PHUIStatusItemView::ICON_REJECT,
            'dark',
            pht('Rejected Prior Diff'));
          break;

        case DifferentialReviewerStatus::STATUS_COMMENTED:
          if ($is_current) {
            $item->setIcon(
              PHUIStatusItemView::ICON_INFO,
              'blue',
              pht('Commented'));
          } else {
            $item->setIcon(
              'info-dark',
              pht('Commented Previously'));
          }
          break;

        case DifferentialReviewerStatus::STATUS_BLOCKING:
          $item->setIcon(
            PHUIStatusItemView::ICON_MINUS,
            'red',
            pht('Blocking Review'));
          break;

        default:
          $item->setIcon(
            PHUIStatusItemView::ICON_QUESTION,
            'bluegrey',
            pht('%s?', $reviewer->getStatus()));
          break;

      }

      $item->setTarget($handle->renderLink());
      $view->addItem($item);
    }

    return $view;
  }

}
