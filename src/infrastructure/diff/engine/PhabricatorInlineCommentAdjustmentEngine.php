<?php

final class PhabricatorInlineCommentAdjustmentEngine
  extends Phobject {

  private $viewer;
  private $inlines;
  private $revision;
  private $oldChangesets;
  private $newChangesets;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setInlines(array $inlines) {
    assert_instances_of($inlines, 'DifferentialInlineComment');
    $this->inlines = $inlines;
    return $this;
  }

  public function getInlines() {
    return $this->inlines;
  }

  public function setOldChangesets(array $old_changesets) {
    assert_instances_of($old_changesets, 'DifferentialChangeset');
    $this->oldChangesets = $old_changesets;
    return $this;
  }

  public function getOldChangesets() {
    return $this->oldChangesets;
  }

  public function setNewChangesets(array $new_changesets) {
    assert_instances_of($new_changesets, 'DifferentialChangeset');
    $this->newChangesets = $new_changesets;
    return $this;
  }

  public function getNewChangesets() {
    return $this->newChangesets;
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  public function getRevision() {
    return $this->revision;
  }

  public function execute() {
    $viewer = $this->getViewer();
    $inlines = $this->getInlines();
    $revision = $this->getRevision();
    $old = $this->getOldChangesets();
    $new = $this->getNewChangesets();

    $no_ghosts = $viewer->compareUserSetting(
      PhabricatorOlderInlinesSetting::SETTINGKEY,
      PhabricatorOlderInlinesSetting::VALUE_GHOST_INLINES_DISABLED);
    if ($no_ghosts) {
      return $inlines;
    }

    $all = array_merge($old, $new);

    $changeset_ids = mpull($inlines, 'getChangesetID');
    $changeset_ids = array_unique($changeset_ids);

    $all_map = mpull($all, null, 'getID');

    // We already have at least some changesets, and we might not need to do
    // any more data fetching. Remove everything we already have so we can
    // tell if we need new stuff.
    foreach ($changeset_ids as $key => $id) {
      if (isset($all_map[$id])) {
        unset($changeset_ids[$key]);
      }
    }

    if ($changeset_ids) {
      $changesets = id(new DifferentialChangesetQuery())
        ->setViewer($viewer)
        ->withIDs($changeset_ids)
        ->execute();
      $changesets = mpull($changesets, null, 'getID');
    } else {
      $changesets = array();
    }
    $changesets += $all_map;

    $id_map = array();
    foreach ($all as $changeset) {
      $id_map[$changeset->getID()] = $changeset->getID();
    }

    // Generate filename maps for older and newer comments. If we're bringing
    // an older comment forward in a diff-of-diffs, we want to put it on the
    // left side of the screen, not the right side. Both sides are "new" files
    // with the same name, so they're both appropriate targets, but the left
    // is a better target conceptually for users because it's more consistent
    // with the rest of the UI, which shows old information on the left and
    // new information on the right.
    $move_here = DifferentialChangeType::TYPE_MOVE_HERE;

    $name_map_old = array();
    $name_map_new = array();
    $move_map = array();
    foreach ($all as $changeset) {
      $changeset_id = $changeset->getID();

      $filenames = array();
      $filenames[] = $changeset->getFilename();

      // If this is the target of a move, also map comments on the old filename
      // to this changeset.
      if ($changeset->getChangeType() == $move_here) {
        $old_file = $changeset->getOldFile();
        $filenames[] = $old_file;
        $move_map[$changeset_id][$old_file] = true;
      }

      foreach ($filenames as $filename) {
        // We update the old map only if we don't already have an entry (oldest
        // changeset persists).
        if (empty($name_map_old[$filename])) {
          $name_map_old[$filename] = $changeset_id;
        }

        // We always update the new map (newest changeset overwrites).
        $name_map_new[$changeset->getFilename()] = $changeset_id;
      }
    }

    $new_id_map = mpull($new, null, 'getID');

    $results = array();
    foreach ($inlines as $inline) {
      $changeset_id = $inline->getChangesetID();
      if (isset($id_map[$changeset_id])) {
        // This inline is legitimately on one of the current changesets, so
        // we can include it in the result set unmodified.
        $results[] = $inline;
        continue;
      }

      $changeset = idx($changesets, $changeset_id);
      if (!$changeset) {
        // Just discard this inline, as it has bogus data.
        continue;
      }

      $target_id = null;

      if (isset($new_id_map[$changeset_id])) {
        $name_map = $name_map_new;
        $is_new = true;
      } else {
        $name_map = $name_map_old;
        $is_new = false;
      }

      $filename = $changeset->getFilename();
      if (isset($name_map[$filename])) {
        // This changeset is on a file with the same name as the current
        // changeset, so we're going to port it forward or backward.
        $target_id = $name_map[$filename];

        $is_move = isset($move_map[$target_id][$filename]);
        if ($is_new) {
          if ($is_move) {
            $reason = pht(
              'This comment was made on a file with the same name as the '.
              'file this file was moved from, but in a newer diff.');
          } else {
            $reason = pht(
              'This comment was made on a file with the same name, but '.
              'in a newer diff.');
          }
        } else {
          if ($is_move) {
            $reason = pht(
              'This comment was made on a file with the same name as the '.
              'file this file was moved from, but in an older diff.');
          } else {
            $reason = pht(
              'This comment was made on a file with the same name, but '.
              'in an older diff.');
          }
        }
      }


      // If we didn't find a target and this change is the target of a move,
      // look for a match against the old filename.
      if (!$target_id) {
        if ($changeset->getChangeType() == $move_here) {
          $filename = $changeset->getOldFile();
          if (isset($name_map[$filename])) {
            $target_id = $name_map[$filename];
            if ($is_new) {
              $reason = pht(
                'This comment was made on a file which this file was moved '.
                'to, but in a newer diff.');
            } else {
              $reason = pht(
                'This comment was made on a file which this file was moved '.
                'to, but in an older diff.');
            }
          }
        }
      }


      // If we found a changeset to port this comment to, bring it forward
      // or backward and mark it.
      if ($target_id) {
        $diff_id = $changeset->getDiffID();
        $inline_id = $inline->getID();
        $revision_id = $revision->getID();
        $href = "/D{$revision_id}?id={$diff_id}#inline-{$inline_id}";

        $inline
          ->makeEphemeral(true)
          ->setChangesetID($target_id)
          ->setIsGhost(
            array(
              'new' => $is_new,
              'reason' => $reason,
              'href' => $href,
              'originalID' => $changeset->getID(),
            ));

        $results[] = $inline;
      }
    }

    // Filter out the inlines we ported forward which won't be visible because
    // they appear on the wrong side of a file.
    $keep_map = array();
    foreach ($old as $changeset) {
      $keep_map[$changeset->getID()][0] = true;
    }
    foreach ($new as $changeset) {
      $keep_map[$changeset->getID()][1] = true;
    }

    foreach ($results as $key => $inline) {
      $is_new = (int)$inline->getIsNewFile();
      $changeset_id = $inline->getChangesetID();
      if (!isset($keep_map[$changeset_id][$is_new])) {
        unset($results[$key]);
        continue;
      }
    }

    // Adjust inline line numbers to account for content changes across
    // updates and rebases.
    $plan = array();
    $need = array();
    foreach ($results as $inline) {
      $ghost = $inline->getIsGhost();
      if (!$ghost) {
        // If this isn't a "ghost" inline, ignore it.
        continue;
      }

      $src_id = $ghost['originalID'];
      $dst_id = $inline->getChangesetID();

      $xforms = array();

      // If the comment is on the right, transform it through the inverse map
      // back to the left.
      if ($inline->getIsNewFile()) {
        $xforms[] = array($src_id, $src_id, true);
      }

      // Transform it across rebases.
      $xforms[] = array($src_id, $dst_id, false);

      // If the comment is on the right, transform it back onto the right.
      if ($inline->getIsNewFile()) {
        $xforms[] = array($dst_id, $dst_id, false);
      }

      $key = array();
      foreach ($xforms as $xform) {
        list($u, $v, $inverse) = $xform;

        $short = $u.'/'.$v;
        $need[$short] = array($u, $v);

        $part = $u.($inverse ? '<' : '>').$v;
        $key[] = $part;
      }
      $key = implode(',', $key);

      if (empty($plan[$key])) {
        $plan[$key] = array(
          'xforms' => $xforms,
          'inlines' => array(),
        );
      }

      $plan[$key]['inlines'][] = $inline;
    }

    if ($need) {
      $maps = DifferentialLineAdjustmentMap::loadMaps($need);
    } else {
      $maps = array();
    }

    foreach ($plan as $step) {
      $xforms = $step['xforms'];

      $chain = null;
      foreach ($xforms as $xform) {
        list($u, $v, $inverse) = $xform;
        $map = idx(idx($maps, $u, array()), $v);
        if (!$map) {
          continue 2;
        }

        if ($inverse) {
          $map = DifferentialLineAdjustmentMap::newInverseMap($map);
        } else {
          $map = clone $map;
        }

        if ($chain) {
          $chain->addMapToChain($map);
        } else {
          $chain = $map;
        }
      }

      foreach ($step['inlines'] as $inline) {
        $head_line = $inline->getLineNumber();
        $tail_line = ($head_line + $inline->getLineLength());

        $head_info = $chain->mapLine($head_line, false);
        $tail_info = $chain->mapLine($tail_line, true);

        list($head_deleted, $head_offset, $head_line) = $head_info;
        list($tail_deleted, $tail_offset, $tail_line) = $tail_info;

        if ($head_offset !== false) {
          $inline->setLineNumber($head_line + $head_offset);
        } else {
          $inline->setLineNumber($head_line);
          $inline->setLineLength($tail_line - $head_line);
        }
      }
    }

    return $results;
  }

}
