<?php

final class DifferentialRevisionReviewersTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.reviewers';
  const EDITKEY = 'reviewers';

  public function generateOldValue($object) {
    $reviewers = $object->getReviewerStatus();
    $reviewers = mpull($reviewers, 'getStatus', 'getReviewerPHID');
    return $reviewers;
  }

  public function generateNewValue($object, $value) {
    $actor = $this->getActor();

    $datasource = id(new DifferentialBlockingReviewerDatasource())
      ->setViewer($actor);

    $reviewers = $this->generateOldValue($object);
    $old_reviewers = $reviewers;

    // First, remove any reviewers we're getting rid of.
    $rem = idx($value, '-', array());
    $rem = $datasource->evaluateTokens($rem);
    foreach ($rem as $phid) {
      unset($reviewers[$phid]);
    }

    $add = idx($value, '+', array());
    $add = $datasource->evaluateTokens($add);
    $add_map = array();
    foreach ($add as $spec) {
      if (!is_array($spec)) {
        $phid = $spec;
        $status = DifferentialReviewerStatus::STATUS_ADDED;
      } else {
        $phid = $spec['phid'];
        $status = $spec['type'];
      }

      $add_map[$phid] = $status;
    }

    $set = idx($value, '=', null);
    if ($set !== null) {
      $set = $datasource->evaluateTokens($set);
      foreach ($set as $spec) {
        if (!is_array($spec)) {
          $phid = $spec;
          $status = DifferentialReviewerStatus::STATUS_ADDED;
        } else {
          $phid = $spec['phid'];
          $status = $spec['type'];
        }

        $add_map[$phid] = $status;
      }

      // We treat setting reviewers as though they were being added to an
      // empty list, so we can share more code between pathways.
      $reviewers = array();
    }

    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;
    foreach ($add_map as $phid => $new_status) {
      $old_status = idx($old_reviewers, $phid);

      // If we have an old status and this didn't make the reviewer blocking
      // or nonblocking, just retain the old status. This makes sure we don't
      // throw away rejects, accepts, etc.
      if ($old_status) {
        $was_blocking = ($old_status == $status_blocking);
        $now_blocking = ($new_status == $status_blocking);

        $is_block = ($now_blocking && !$was_blocking);
        $is_unblock = (!$now_blocking && $was_blocking);

        if (!$is_block && !$is_unblock) {
          $reviewers[$phid] = $old_status;
          continue;
        }
      }

      $reviewers[$phid] = $new_status;
    }

    return $reviewers;
  }

  public function getTransactionHasEffect($object, $old, $new) {
    // At least for now, we ignore transactions which ONLY reorder reviewers
    // without making any actual changes.
    ksort($old);
    ksort($new);
    return ($old !== $new);
  }

  public function applyExternalEffects($object, $value) {
    $src_phid = $object->getPHID();

    $old = $this->generateOldValue($object);
    $new = $value;
    $edge_type = DifferentialRevisionHasReviewerEdgeType::EDGECONST;

    $editor = new PhabricatorEdgeEditor();

    $rem = array_diff_key($old, $new);
    foreach ($rem as $dst_phid => $status) {
      $editor->removeEdge($src_phid, $edge_type, $dst_phid);
    }

    foreach ($new as $dst_phid => $status) {
      $old_status = idx($old, $dst_phid);
      if ($old_status === $status) {
        continue;
      }

      $data = array(
        'data' => array(
          'status' => $status,

          // TODO: This seemes like it's buggy before the Modular Transactions
          // changes. Figure out what's going on here? We don't have a very
          // clean way to get the active diff ID right now.
          'diffID' => null,
        ),
      );

      $editor->addEdge($src_phid, $edge_type, $dst_phid, $data);
    }

    $editor->save();
  }

  public function getTitle() {
    return $this->renderReviewerEditTitle(false);
  }

  public function getTitleForFeed() {
    return $this->renderReviewerEditTitle(true);
  }

  private function renderReviewerEditTitle($is_feed) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $rem = array_diff_key($old, $new);
    $add = array_diff_key($new, $old);
    $rem_phids = array_keys($rem);
    $add_phids = array_keys($add);
    $total_count = count($rem) + count($add);

    $parts = array();

    if ($rem && $add) {
      if ($is_feed) {
        $parts[] = pht(
          '%s edited %s reviewer(s) for %s, added %s: %s; removed %s: %s.',
          $this->renderAuthor(),
          new PhutilNumber($total_count),
          $this->renderObject(),
          phutil_count($add_phids),
          $this->renderHandleList($add_phids),
          phutil_count($rem_phids),
          $this->renderHandleList($rem_phids));
      } else {
        $parts[] = pht(
          '%s edited %s reviewer(s), added %s: %s; removed %s: %s.',
          $this->renderAuthor(),
          new PhutilNumber($total_count),
          phutil_count($add_phids),
          $this->renderHandleList($add_phids),
          phutil_count($rem_phids),
          $this->renderHandleList($rem_phids));
      }
    } else if ($add) {
      if ($is_feed) {
        $parts[] = pht(
          '%s added %s reviewer(s) for %s: %s.',
          $this->renderAuthor(),
          phutil_count($add_phids),
          $this->renderObject(),
          $this->renderHandleList($add_phids));
      } else {
        $parts[] = pht(
          '%s added %s reviewer(s): %s.',
          $this->renderAuthor(),
          phutil_count($add_phids),
          $this->renderHandleList($add_phids));
      }
    } else if ($rem) {
      if ($is_feed) {
        $parts[] = pht(
          '%s removed %s reviewer(s) for %s: %s.',
          $this->renderAuthor(),
          phutil_count($rem_phids),
          $this->renderObject(),
          $this->renderHandleList($rem_phids));
      } else {
        $parts[] = pht(
          '%s removed %s reviewer(s): %s.',
          $this->renderAuthor(),
          phutil_count($rem_phids),
          $this->renderHandleList($rem_phids));
      }
    }

    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;
    $blocks = array();
    $unblocks = array();
    foreach ($new as $phid => $new_status) {
      $old_status = idx($old, $phid);
      if (!$old_status) {
        continue;
      }

      $was_blocking = ($old_status == $status_blocking);
      $now_blocking = ($new_status == $status_blocking);

      $is_block = ($now_blocking && !$was_blocking);
      $is_unblock = (!$now_blocking && $was_blocking);

      if ($is_block) {
        $blocks[] = $phid;
      }
      if ($is_unblock) {
        $unblocks[] = $phid;
      }
    }

    $total_count = count($blocks) + count($unblocks);

    if ($blocks && $unblocks) {
      if ($is_feed) {
        $parts[] = pht(
          '%s changed %s blocking reviewer(s) for %s, added %s: %s; removed '.
          '%s: %s.',
          $this->renderAuthor(),
          new PhutilNumber($total_count),
          $this->renderObject(),
          phutil_count($blocks),
          $this->renderHandleList($blocks),
          phutil_count($unblocks),
          $this->renderHandleList($unblocks));
      } else {
        $parts[] = pht(
          '%s changed %s blocking reviewer(s), added %s: %s; removed %s: %s.',
          $this->renderAuthor(),
          new PhutilNumber($total_count),
          phutil_count($blocks),
          $this->renderHandleList($blocks),
          phutil_count($unblocks),
          $this->renderHandleList($unblocks));
      }
    } else if ($blocks) {
      if ($is_feed) {
        $parts[] = pht(
          '%s added %s blocking reviewer(s) for %s: %s.',
          $this->renderAuthor(),
          phutil_count($blocks),
          $this->renderObject(),
          $this->renderHandleList($blocks));
      } else {
        $parts[] = pht(
          '%s added %s blocking reviewer(s): %s.',
          $this->renderAuthor(),
          phutil_count($blocks),
          $this->renderHandleList($blocks));
      }
    } else if ($unblocks) {
      if ($is_feed) {
        $parts[] = pht(
          '%s removed %s blocking reviewer(s) for %s: %s.',
          $this->renderAuthor(),
          phutil_count($unblocks),
          $this->renderObject(),
          $this->renderHandleList($unblocks));
      } else {
        $parts[] = pht(
          '%s removed %s blocking reviewer(s): %s.',
          $this->renderAuthor(),
          phutil_count($unblocks),
          $this->renderHandleList($unblocks));
      }
    }

    if ($this->isTextMode()) {
      return implode(' ', $parts);
    } else {
      return phutil_implode_html(' ', $parts);
    }
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    if (!$xactions) {
      // If we aren't applying new reviewer transactions, just bail. We need
      // reviewers to be attached to the revision continue validation, and
      // they won't always be (for example, when mentioning a revision).
      return $errors;
    }

    $author_phid = $object->getAuthorPHID();
    $config_self_accept_key = 'differential.allow-self-accept';
    $allow_self_accept = PhabricatorEnv::getEnvConfig($config_self_accept_key);

    $old = $this->generateOldValue($object);
    foreach ($xactions as $xaction) {
      $new = $this->generateNewValue($object, $xaction->getNewValue());

      $add = array_diff_key($new, $old);
      if (!$add) {
        continue;
      }

      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($actor)
        ->withPHIDs(array_keys($add))
        ->execute();
      $objects = mpull($objects, null, 'getPHID');

      foreach ($add as $phid => $status) {
        if (!isset($objects[$phid])) {
          $errors[] = $this->newInvalidError(
            pht(
              'Reviewer "%s" is not a valid object.',
              $phid),
            $xaction);
          continue;
        }

        switch (phid_get_type($phid)) {
          case PhabricatorPeopleUserPHIDType::TYPECONST:
          case PhabricatorOwnersPackagePHIDType::TYPECONST:
          case PhabricatorProjectProjectPHIDType::TYPECONST:
            break;
          default:
            $errors[] = $this->newInvalidError(
              pht(
                'Reviewer "%s" must be a user, a package, or a project.',
                $phid),
              $xaction);
            continue 2;
        }

        // NOTE: This weird behavior around commandeering is a bit unorthodox,
        // but this restriction is an unusual one.

        $is_self = ($phid === $author_phid);
        if ($is_self && !$allow_self_accept) {
          if (!$xaction->getIsCommandeerSideEffect()) {
            $errors[] = $this->newInvalidError(
              pht('The author of a revision can not be a reviewer.'),
              $xaction);
            continue;
          }
        }
      }
    }

    return $errors;
  }

}
