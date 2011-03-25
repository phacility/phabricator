<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class HeraldDifferentialRevisionAdapter extends HeraldObjectAdapter {

  protected $revision;
  protected $changesets;
  protected $diff = null;

  protected $explicitCCs;
  protected $explicitReviewers;
  protected $forbiddenCCs;
  protected $forbiddenReviewers;

  protected $newCCs = array();
  protected $remCCs = array();

  public function __construct(DifferentialRevision $revision) {
    $revision->loadRelationships();
    $this->revision = $revision;
  }

  public function setDiff(Diff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function setExplicitCCs($explicit_ccs) {
    $this->explicitCCs = $explicit_ccs;
    return $this;
  }

  public function setExplicitReviewers($explicit_reviewers) {
    $this->explicitReviewers = $explicit_reviewers;
    return $this;
  }

  public function setForbiddenCCs($forbidden_ccs) {
    $this->forbiddenCCs = $forbidden_ccs;
    return $this;
  }

  public function setForbiddenReviewers($forbidden_reviewers) {
    $this->forbiddenReviewers = $forbidden_reviewers;
    return $this;
  }

  public function getCCsAddedByHerald() {
    return array_diff_key($this->newCCs, $this->remCCs);
  }

  public function getCCsRemovedByHerald() {
    return $this->remCCs;
  }

  public function getPHID() {
    return $this->revision->getPHID();
  }

  public function getHeraldName() {
    return $this->revision->getTitle();
  }

  public function getHeraldTypeName() {
    return HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL;
  }

  protected function loadChangesets() {
    if ($this->changesets) {
      return $this->changesets;
    }
    $diff = $this->loadDiff();
    $changes = $diff->getChangesets();
    return ($this->changesets = $changes);
  }

  protected function loadDiff() {
    if ($this->diff === null) {
      $this->diff = $this->revision->getActiveDiff();
    }
    return $this->diff;
  }

  protected function getContentDictionary() {
    $changes = $this->loadChangesets();

    $hunks = array();
    if ($changes) {
      $hunks = id(new DifferentialHunk())->loadAllwhere(
        'changesetID in (%Ld)',
        mpull($changes, 'getID'));
    }

    $dict = array();
    $hunks = mgroup($hunks, 'getChangesetID');
    $changes = mpull($changes, null, 'getID');
    foreach ($changes as $id => $change) {
      $filename = $change->getFilename();
      $content = array();
      foreach (idx($hunks, $id, array()) as $hunk) {
        $content[] = $hunk->makeChanges();
      }
      $dict[$filename] = implode("\n", $content);
    }

    return $dict;
  }

  public function getHeraldField($field) {
    switch ($field) {
      case HeraldFieldConfig::FIELD_TITLE:
        return $this->revision->getTitle();
        break;
      case HeraldFieldConfig::FIELD_BODY:
        return $this->revision->getSummary()."\n".
               $this->revision->getTestPlan();
        break;
      case HeraldFieldConfig::FIELD_AUTHOR:
        return $this->revision->getAuthorPHID();
        break;
      case HeraldFieldConfig::FIELD_DIFF_FILE:
        $changes = $this->loadChangesets();
        return array_values(mpull($changes, 'getFilename'));
      case HeraldFieldConfig::FIELD_CC:
        if (isset($this->explicitCCs)) {
          return array_keys($this->explicitCCs);
        } else {
          return $this->revision->getCCPHIDs();
        }
      case HeraldFieldConfig::FIELD_REVIEWERS:
        if (isset($this->explicitReviewers)) {
          return array_keys($this->explicitReviewers);
        } else {
          return $this->revision->getReviewers();
        }
/* TODO
      case HeraldFieldConfig::FIELD_REPOSITORY:
        $id = $this->revision->getRepositoryID();
        if (!$id) {
          return null;
        }
        require_module_lazy('intern/repository');
        $repository = RepositoryRef::getByID($id);
        if (!$repository) {
          return null;
        }
        return $repository->getFBID();
*/
      case HeraldFieldConfig::FIELD_DIFF_CONTENT:
        return $this->getContentDictionary();
/* TODO
      case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE:
        return mpull(
          DiffOwners::getPackages($this->loadDiff()),
          'getFBID');
*/
/* TODO
      case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE_OWNER:
        return DiffOwners::getOwners($this->loadDiff());
*/
      default:
        throw new Exception("Invalid field '{$field}'.");
    }
  }

  public function applyHeraldEffects(array $effects) {
    $result = array();
    if ($this->explicitCCs) {
      $effect = new HeraldEffect();
      $effect->setAction(HeraldActionConfig::ACTION_ADD_CC);
      $effect->setTarget(array_keys($this->explicitCCs));
      $effect->setReason(
        'CCs provided explicitly by revision author or carried over from a '.
        'previous version of the revision.');
      $result[] = new HeraldApplyTranscript(
        $effect,
        true,
        'Added addresses to CC list.');
    }

    $forbidden_ccs = array_fill_keys(
      nonempty($this->forbiddenCCs, array()),
      true);

    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case HeraldActionConfig::ACTION_NOTHING:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            'OK, did nothing.');
          break;
        case HeraldActionConfig::ACTION_ADD_CC:
          $base_target = $effect->getTarget();
          $forbidden = array();
          foreach ($base_target as $key => $fbid) {
            if (isset($forbidden_ccs[$fbid])) {
              $forbidden[] = $fbid;
              unset($base_target[$key]);
            } else {
              $this->newCCs[$fbid] = true;
            }
          }

          if ($forbidden) {
            $failed = clone $effect;
            $failed->setTarget($forbidden);
            if ($base_target) {
              $effect->setTarget($base_target);
              $result[] = new HeraldApplyTranscript(
                $effect,
                true,
                'Added these addresses to CC list. Others could not be added.');
            }
            $result[] = new HeraldApplyTranscript(
              $failed,
              false,
              'CC forbidden, these addresses have unsubscribed.');
          } else {
            $result[] = new HeraldApplyTranscript(
              $effect,
              true,
              'Added addresses to CC list.');
          }
          break;
        case HeraldActionConfig::ACTION_REMOVE_CC:
          foreach ($effect->getTarget() as $fbid) {
            $this->remCCs[$fbid] = true;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            'Removed addresses from CC list.');
          break;
        default:
          throw new Exception("No rules to handle action '{$action}'.");
      }
    }
    return $result;
  }
}
