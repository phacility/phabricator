<?php

final class ReleephDiffChurnFieldSpecification
  extends ReleephFieldSpecification {

  const REJECTIONS_WEIGHT =  30;
  const COMMENTS_WEIGHT   =   7;
  const UPDATES_WEIGHT    =  10;
  const MAX_POINTS        = 100;

  public function getName() {
    return 'Churn';
  }

  public function renderValueForHeaderView() {
    $diff_rev = $this->getReleephRequest()->loadDifferentialRevision();
    if (!$diff_rev) {
      return null;
    }

    $diff_rev = $this->getReleephRequest()->loadDifferentialRevision();
    $comments = $diff_rev->loadRelatives(
      new DifferentialComment(),
      'revisionID');

    $counts = array();
    foreach ($comments as $comment) {
      $action = $comment->getAction();
      if (!isset($counts[$action])) {
        $counts[$action] = 0;
      }
      $counts[$action] += 1;
    }

    // 'none' action just means a plain comment
    $comments   = idx($counts, 'none',     0);
    $rejections = idx($counts, 'reject',   0);
    $updates    = idx($counts, 'update',   0);

    $points =
      self::REJECTIONS_WEIGHT * $rejections +
      self::COMMENTS_WEIGHT * $comments +
      self::UPDATES_WEIGHT * $updates;

    if ($points === 0) {
      $points = 0.15 * self::MAX_POINTS;
      $blurb = 'Silent diff';
    } else {
      $parts = array();
      if ($rejections) {
        $parts[] = pht('%d rejection(s)', $rejections);
      }
      if ($comments) {
        $parts[] = pht('%d comment(s)', $comments);
      }
      if ($updates) {
        $parts[] = pht('%d update(s)', $updates);
      }

      if (count($parts) === 0) {
        $blurb = '';
      } else if (count($parts) === 1) {
        $blurb = head($parts);
      } else {
        $last = array_pop($parts);
        $blurb = implode(', ', $parts).' and '.$last;
      }
    }

    return id(new AphrontProgressBarView())
      ->setValue($points)
      ->setMax(self::MAX_POINTS)
      ->setCaption($blurb)
      ->render();
  }

}
