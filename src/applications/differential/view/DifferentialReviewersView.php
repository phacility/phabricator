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
    $reviewers = $this->reviewers;

    $view = new PHUIStatusListView();

    // Move resigned reviewers to the bottom.
    $head = array();
    $tail = array();
    foreach ($reviewers as $key => $reviewer) {
      if ($reviewer->isResigned()) {
        $tail[$key] = $reviewer;
      } else {
        $head[$key] = $reviewer;
      }
    }

    $reviewers = $head + $tail;
    foreach ($reviewers as $reviewer) {
      $phid = $reviewer->getReviewerPHID();
      $handle = $this->handles[$phid];

      $action_phid = $reviewer->getLastActionDiffPHID();
      $is_current_action = $this->isCurrent($action_phid);

      $comment_phid = $reviewer->getLastCommentDiffPHID();
      $is_current_comment = $this->isCurrent($comment_phid);

      $item = new PHUIStatusItemView();

      $item->setHighlighted($reviewer->hasAuthority($viewer));

      switch ($reviewer->getReviewerStatus()) {
        case DifferentialReviewerStatus::STATUS_ADDED:
          if ($comment_phid) {
            if ($is_current_comment) {
              $item->setIcon(
                'fa-comment',
                'blue',
                pht('Commented'));
            } else {
              $item->setIcon(
                'fa-comment-o',
                'bluegrey',
                pht('Commented Previously'));
            }
          } else {
            $item->setIcon(
              PHUIStatusItemView::ICON_OPEN,
              'bluegrey',
              pht('Review Requested'));
          }
          break;

        case DifferentialReviewerStatus::STATUS_ACCEPTED:
          if ($is_current_action) {
            $item->setIcon(
              PHUIStatusItemView::ICON_ACCEPT,
              'green',
              pht('Accepted'));
          } else {
            $item->setIcon(
              'fa-check-circle-o',
              'bluegrey',
              pht('Accepted Prior Diff'));
          }
          break;

        case DifferentialReviewerStatus::STATUS_REJECTED:
          if ($is_current_action) {
            $item->setIcon(
              PHUIStatusItemView::ICON_REJECT,
              'red',
              pht('Requested Changes'));
          } else {
            $item->setIcon(
              'fa-times-circle-o',
              'bluegrey',
              pht('Requested Changes to Prior Diff'));
          }
          break;

        case DifferentialReviewerStatus::STATUS_BLOCKING:
          $item->setIcon(
            PHUIStatusItemView::ICON_MINUS,
            'red',
            pht('Blocking Review'));
          break;

        case DifferentialReviewerStatus::STATUS_RESIGNED:
          $item->setIcon(
            'fa-times',
            'grey',
            pht('Resigned'));
          break;

        default:
          $item->setIcon(
            PHUIStatusItemView::ICON_QUESTION,
            'bluegrey',
            pht('%s?', $reviewer->getReviewerStatus()));
          break;

      }

      $item->setTarget($handle->renderHovercardLink());
      $view->addItem($item);
    }

    return $view;
  }

  private function isCurrent($action_phid) {
    if (!$this->diff) {
      echo "A\n";
      return true;
    }

    if (!$action_phid) {
      return true;
    }

    $diff_phid = $this->diff->getPHID();
    if (!$diff_phid) {
      return true;
    }

    if ($diff_phid == $action_phid) {
      return true;
    }

    return false;
  }

}
