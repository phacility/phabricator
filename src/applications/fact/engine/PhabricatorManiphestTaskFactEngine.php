<?php

final class PhabricatorManiphestTaskFactEngine
  extends PhabricatorTransactionFactEngine {

  public function newFacts() {
    return array(
      id(new PhabricatorCountFact())
        ->setKey('tasks.count.create'),

      id(new PhabricatorCountFact())
        ->setKey('tasks.open-count.create'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.open-count.status'),

      id(new PhabricatorCountFact())
        ->setKey('tasks.count.create.project'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.count.assign.project'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.open-count.create.project'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.open-count.status.project'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.open-count.assign.project'),

      id(new PhabricatorCountFact())
        ->setKey('tasks.count.create.owner'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.count.assign.owner'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.open-count.create.owner'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.open-count.status.owner'),
      id(new PhabricatorCountFact())
        ->setKey('tasks.open-count.assign.owner'),

      id(new PhabricatorPointsFact())
        ->setKey('tasks.points.create'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.points.score'),

      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.create'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.status'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.score'),

      id(new PhabricatorPointsFact())
        ->setKey('tasks.points.create.project'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.points.assign.project'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.points.score.project'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.create.project'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.status.project'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.score.project'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.assign.project'),

      id(new PhabricatorPointsFact())
        ->setKey('tasks.points.create.owner'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.points.assign.owner'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.points.score.owner'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.create.owner'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.status.owner'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.score.owner'),
      id(new PhabricatorPointsFact())
        ->setKey('tasks.open-points.assign.owner'),
    );
  }

  public function supportsDatapointsForObject(PhabricatorLiskDAO $object) {
    return ($object instanceof ManiphestTask);
  }

  public function newDatapointsForObject(PhabricatorLiskDAO $object) {
    $xaction_groups = $this->newTransactionGroupsForObject($object);

    $old_open = false;
    $old_points = 0;
    $old_owner = null;
    $project_map = array();
    $object_phid = $object->getPHID();
    $is_create = true;

    $specs = array();
    $datapoints = array();
    foreach ($xaction_groups as $xaction_group) {
      $add_projects = array();
      $rem_projects = array();

      $new_open = $old_open;
      $new_points = $old_points;
      $new_owner = $old_owner;

      if ($is_create) {
        // Assume tasks start open.
        // TODO: This might be a questionable assumption?
        $new_open = true;
      }

      $group_epoch = last($xaction_group)->getDateCreated();
      foreach ($xaction_group as $xaction) {
        $old_value = $xaction->getOldValue();
        $new_value = $xaction->getNewValue();
        switch ($xaction->getTransactionType()) {
          case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
            $new_open = !ManiphestTaskStatus::isClosedStatus($new_value);
            break;
          case ManiphestTaskMergedIntoTransaction::TRANSACTIONTYPE:
            // When a task is merged into another task, it is changed to a
            // closed status without generating a separate status transaction.
            $new_open = false;
            break;
          case ManiphestTaskPointsTransaction::TRANSACTIONTYPE:
            $new_points = (int)$xaction->getNewValue();
            break;
          case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
            $new_owner = $xaction->getNewValue();
            break;
          case PhabricatorTransactions::TYPE_EDGE:
            $edge_type = $xaction->getMetadataValue('edge:type');
            switch ($edge_type) {
              case PhabricatorProjectObjectHasProjectEdgeType::EDGECONST:
                $record = PhabricatorEdgeChangeRecord::newFromTransaction(
                  $xaction);
                $add_projects += array_fuse($record->getAddedPHIDs());
                $rem_projects += array_fuse($record->getRemovedPHIDs());
                break;
            }
            break;
        }
      }

      // If a project was both added and removed, moot it.
      $mix_projects = array_intersect_key($add_projects, $rem_projects);
      $add_projects = array_diff_key($add_projects, $mix_projects);
      $rem_projects = array_diff_key($rem_projects, $mix_projects);

      $project_sets = array(
        array(
          'phids' => $rem_projects,
          'scale' => -1,
        ),
        array(
          'phids' => $add_projects,
          'scale' => 1,
        ),
      );

      if ($is_create) {
        $action = 'create';
        $action_points = $new_points;
        $include_open = $new_open;
      } else {
        $action = 'assign';
        $action_points = $old_points;
        $include_open = $old_open;
      }

      foreach ($project_sets as $project_set) {
        $scale = $project_set['scale'];
        foreach ($project_set['phids'] as $project_phid) {
          if ($include_open) {
            $specs[] = array(
              "tasks.open-count.{$action}.project",
              1 * $scale,
              $project_phid,
            );

            $specs[] = array(
              "tasks.open-points.{$action}.project",
              $action_points * $scale,
              $project_phid,
            );
          }

          $specs[] = array(
            "tasks.count.{$action}.project",
            1 * $scale,
            $project_phid,
          );

          $specs[] = array(
            "tasks.points.{$action}.project",
            $action_points * $scale,
            $project_phid,
          );

          if ($scale < 0) {
            unset($project_map[$project_phid]);
          } else {
            $project_map[$project_phid] = $project_phid;
          }
        }
      }

      if ($new_owner !== $old_owner) {
        $owner_sets = array(
          array(
            'phid' => $old_owner,
            'scale' => -1,
          ),
          array(
            'phid' => $new_owner,
            'scale' => 1,
          ),
        );

        foreach ($owner_sets as $owner_set) {
          $owner_phid = $owner_set['phid'];
          if ($owner_phid === null) {
            continue;
          }

          $scale = $owner_set['scale'];

          if ($old_open != $new_open) {
            $specs[] = array(
              "tasks.open-count.{$action}.owner",
              1 * $scale,
              $owner_phid,
            );

            $specs[] = array(
              "tasks.open-points.{$action}.owner",
              $action_points * $scale,
              $owner_phid,
            );
          }

          $specs[] = array(
            "tasks.count.{$action}.owner",
            1 * $scale,
            $owner_phid,
          );

          if ($action_points) {
            $specs[] = array(
              "tasks.points.{$action}.owner",
              $action_points * $scale,
              $owner_phid,
            );
          }
        }
      }

      if ($is_create) {
        $specs[] = array(
          'tasks.count.create',
          1,
        );

        $specs[] = array(
          'tasks.points.create',
          $new_points,
        );

        if ($new_open) {
          $specs[] = array(
            'tasks.open-count.create',
            1,
          );
          $specs[] = array(
            'tasks.open-points.create',
            $new_points,
          );
        }
      } else if ($new_open !== $old_open) {
        if ($new_open) {
          $scale = 1;
        } else {
          $scale = -1;
        }

        $specs[] = array(
          'tasks.open-count.status',
          1 * $scale,
        );

        $specs[] = array(
          'tasks.open-points.status',
          $action_points * $scale,
        );

        if ($new_owner !== null) {
          $specs[] = array(
            'tasks.open-count.status.owner',
            1 * $scale,
            $new_owner,
          );
          $specs[] = array(
            'tasks.open-points.status.owner',
            $action_points * $scale,
            $new_owner,
          );
        }

        foreach ($project_map as $project_phid) {
          $specs[] = array(
            'tasks.open-count.status.project',
            1 * $scale,
            $project_phid,
          );
          $specs[] = array(
            'tasks.open-points.status.project',
            $action_points * $scale,
            $project_phid,
          );
        }
      }

      // The "score" facts only apply to rescoring tasks which already
      // exist, so we skip them if the task is being created.
      if (($new_points !== $old_points) && !$is_create) {
        $delta = ($new_points - $old_points);

        $specs[] = array(
          'tasks.points.score',
          $delta,
        );

        foreach ($project_map as $project_phid) {
          $specs[] = array(
            'tasks.points.score.project',
            $delta,
            $project_phid,
          );

          if ($old_open && $new_open) {
            $specs[] = array(
              'tasks.open-points.score.project',
              $delta,
              $project_phid,
            );
          }
        }

        if ($new_owner !== null) {
          $specs[] = array(
            'tasks.points.score.owner',
            $delta,
            $new_owner,
          );

          if ($old_open && $new_open) {
            $specs[] = array(
              'tasks.open-points.score.owner',
              $delta,
              $new_owner,
            );
          }
        }

        if ($old_open && $new_open) {
          $specs[] = array(
            'tasks.open-points.score',
            $delta,
          );
        }
      }

      $old_points = $new_points;
      $old_open = $new_open;
      $old_owner = $new_owner;

      foreach ($specs as $spec) {
        $spec_key = $spec[0];
        $spec_value = $spec[1];

        // Don't write any facts with a value of 0. The "count" facts never
        // have a value of 0, and the "points" facts aren't meaningful if
        // they have a value of 0.
        if ($spec_value == 0) {
          continue;
        }

        $datapoint = $this->getFact($spec_key)
          ->newDatapoint();

        $datapoint
          ->setObjectPHID($object_phid)
          ->setValue($spec_value)
          ->setEpoch($group_epoch);

        if (isset($spec[2])) {
          $datapoint->setDimensionPHID($spec[2]);
        }

        $datapoints[] = $datapoint;
      }

      $specs = array();
      $is_create = false;
    }

    return $datapoints;
  }


}
