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
    $diff = $this->diff;
    $handles = $this->handles;

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

    PhabricatorPolicyFilterSet::loadHandleViewCapabilities(
      $viewer,
      $handles,
      array($diff));

    $reviewers = $head + $tail;
    foreach ($reviewers as $reviewer) {
      $phid = $reviewer->getReviewerPHID();
      $handle = $handles[$phid];

      $action_phid = $reviewer->getLastActionDiffPHID();
      $is_current_action = $this->isCurrent($action_phid);
      $is_voided = (bool)$reviewer->getVoidedPHID();

      $comment_phid = $reviewer->getLastCommentDiffPHID();
      $is_current_comment = $this->isCurrent($comment_phid);

      $item = new PHUIStatusItemView();

      $item->setHighlighted($reviewer->hasAuthority($viewer));

      // If someone other than the reviewer acted on the reviewer's behalf,
      // show who is responsible for the current state. This is usually a
      // user accepting for a package or project.
      $authority_phid = $reviewer->getLastActorPHID();
      if ($authority_phid && ($authority_phid !== $phid)) {
        $authority_name = $viewer->renderHandle($authority_phid)
          ->setAsText(true);
      } else {
        $authority_name = null;
      }

      switch ($reviewer->getReviewerStatus()) {
        case DifferentialReviewerStatus::STATUS_ADDED:
          if ($comment_phid) {
            if ($is_current_comment) {
              $icon = 'fa-comment';
              $color = 'blue';
              $label = pht('Commented');
            } else {
              $icon = 'fa-comment-o';
              $color = 'bluegrey';
              $label = pht('Commented Previously');
            }
          } else {
            $icon = PHUIStatusItemView::ICON_OPEN;
            $color = 'bluegrey';
            $label = pht('Review Requested');
          }
          break;

        case DifferentialReviewerStatus::STATUS_ACCEPTED:
          if ($is_current_action && !$is_voided) {
            $icon = PHUIStatusItemView::ICON_ACCEPT;
            $color = 'green';
            if ($authority_name !== null) {
              $label = pht('Accepted (by %s)', $authority_name);
            } else {
              $label = pht('Accepted');
            }
          } else {
            $icon = 'fa-check-circle-o';
            $color = 'bluegrey';

            if (!$is_current_action && $is_voided) {
              // The reviewer accepted the revision, but later the author
              // used "Request Review" to request an updated review.
              $label = pht('Accepted Earlier');
            } else if ($authority_name !== null) {
              $label = pht('Accepted Prior Diff (by %s)', $authority_name);
            } else {
              $label = pht('Accepted Prior Diff');
            }
          }
          break;

        case DifferentialReviewerStatus::STATUS_REJECTED:
          if ($is_current_action) {
            $icon = PHUIStatusItemView::ICON_REJECT;
            $color = 'red';
            if ($authority_name !== null) {
              $label = pht('Requested Changes (by %s)', $authority_name);
            } else {
              $label = pht('Requested Changes');
            }
          } else {
            $icon = 'fa-times-circle-o';
            $color = 'red';
            if ($authority_name !== null) {
              $label = pht(
                'Requested Changes to Prior Diff (by %s)',
                $authority_name);
            } else {
              $label = pht('Requested Changes to Prior Diff');
            }
          }
          break;

        case DifferentialReviewerStatus::STATUS_BLOCKING:
          $icon = PHUIStatusItemView::ICON_MINUS;
          $color = 'red';
          $label = pht('Blocking Review');
          break;

        case DifferentialReviewerStatus::STATUS_RESIGNED:
          $icon = 'fa-times';
          $color = 'grey';
          $label = pht('Resigned');
          break;

        default:
          $icon = PHUIStatusItemView::ICON_QUESTION;
          $color = 'bluegrey';
          $label = pht('Unknown ("%s")', $reviewer->getReviewerStatus());
          break;

      }

      $item->setIcon($icon, $color, $label);
      $item->setTarget(
        $handle->renderHovercardLink(
          null,
          $diff->getPHID()));

      if ($reviewer->isPackage()) {
        if (!$reviewer->getChangesets()) {
          $item->setNote(pht('(Owns No Changed Paths)'));
        }
      }

      if ($handle->hasCapabilities()) {
        if (!$handle->hasViewCapability($diff)) {
          $item
            ->setIcon('fa-eye-slash', 'red')
            ->setNote(pht('No View Permission'))
            ->setIsExiled(true);
        }
      }

      $view->addItem($item);
    }

    return $view;
  }

  private function isCurrent($action_phid) {
    if (!$this->diff) {
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
