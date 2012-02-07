<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class PhabricatorProjectEditor {

  private $project;
  private $user;
  private $projectName;

  private $addAffiliations;
  private $remAffiliations;

  public function __construct(PhabricatorProject $project) {
    $this->project = $project;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function applyTransactions(array $transactions) {
    if (!$this->user) {
      throw new Exception('Call setUser() before save()!');
    }
    $user = $this->user;

    $project = $this->project;

    $is_new = !$project->getID();

    if ($is_new) {
      $project->setAuthorPHID($user->getPHID());
    }

    foreach ($transactions as $key => $xaction) {
      $type = $xaction->getTransactionType();

      $this->setTransactionOldValue($project, $xaction);

      if (!$this->transactionHasEffect($xaction)) {
        unset($transactions[$key]);
        continue;
      }

      $this->applyTransactionEffect($project, $xaction);
    }

    if (!$transactions) {
      return $this;
    }

    try {
      $project->save();

      foreach ($transactions as $xaction) {
        $xaction->setAuthorPHID($user->getPHID());
        $xaction->setProjectID($project->getID());
        $xaction->save();
      }

      foreach ($this->remAffiliations as $affil) {
        $affil->delete();
      }

      foreach ($this->addAffiliations as $affil) {
        $affil->setProjectPHID($project->getPHID());
        $affil->save();
      }

      foreach ($transactions as $xaction) {
        $this->publishTransactionStory($project, $xaction);
      }

    } catch (AphrontQueryDuplicateKeyException $ex) {
      // We already validated the slug, but might race. Try again to see if
      // that's the issue. If it is, we'll throw a more specific exception. If
      // not, throw the original exception.
      $this->validateName($project);
      throw $ex;
    }

    // TODO: If we rename a project, we should move its Phriction page. Do
    // that once Phriction supports document moves.

    return $this;
  }

  private function validateName(PhabricatorProject $project) {
    $slug = $project->getPhrictionSlug();
    $name = $project->getName();

    if ($slug == '/') {
      throw new PhabricatorProjectNameCollisionException(
        "Project names must be unique and contain some letters or numbers.");
    }

    $id = $project->getID();
    $collision = id(new PhabricatorProject())->loadOneWhere(
      '(name = %s OR phrictionSlug = %s) AND id %Q %nd',
      $name,
      $slug,
      $id ? '!=' : 'IS NOT',
      $id ? $id : null);

    if ($collision) {
      $other_name = $collision->getName();
      $other_id = $collision->getID();
      throw new PhabricatorProjectNameCollisionException(
        "Project names must be unique. The name '{$name}' is too similar to ".
        "the name of another project, '{$other_name}' (Project ID: ".
        "{$other_id}). Choose a unique name.");
    }
  }

  private function setTransactionOldValue(
    PhabricatorProject $project,
    PhabricatorProjectTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorProjectTransactionType::TYPE_NAME:
        $xaction->setOldValue($project->getName());
        break;
      case PhabricatorProjectTransactionType::TYPE_STATUS:
        $xaction->setOldValue($project->getStatus());
        break;
      case PhabricatorProjectTransactionType::TYPE_MEMBERS:
        $affils = $project->loadAffiliations();
        $project->attachAffiliations($affils);

        $old_value = mpull($affils, 'getUserPHID');
        $old_value = array_values($old_value);
        $xaction->setOldValue($old_value);

        $new_value = $xaction->getNewValue();
        $new_value = array_filter($new_value);
        $new_value = array_unique($new_value);
        $new_value = array_values($new_value);
        $xaction->setNewValue($new_value);
        break;
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }
  }

  private function applyTransactionEffect(
    PhabricatorProject $project,
    PhabricatorProjectTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorProjectTransactionType::TYPE_NAME:
        $project->setName($xaction->getNewValue());
        $project->setPhrictionSlug($xaction->getNewValue());
        $this->validateName($project);
        break;
      case PhabricatorProjectTransactionType::TYPE_STATUS:
        $project->setStatus($xaction->getNewValue());
        break;
      case PhabricatorProjectTransactionType::TYPE_MEMBERS:
        $old = array_fill_keys($xaction->getOldValue(), true);
        $new = array_fill_keys($xaction->getNewValue(), true);

        $add = array();
        $rem = array();

        foreach ($project->getAffiliations() as $affil) {
          if (empty($new[$affil->getUserPHID()])) {
            $rem[] = $affil;
          }
        }

        foreach ($new as $phid => $ignored) {
          if (empty($old[$phid])) {
            $affil = new PhabricatorProjectAffiliation();
            $affil->setRole('');
            $affil->setUserPHID($phid);
            $add[] = $affil;
          }
        }

        $this->addAffiliations = $add;
        $this->remAffiliations = $rem;
        break;
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }
  }

  private function publishTransactionStory(
    PhabricatorProject $project,
    PhabricatorProjectTransaction $xaction) {

    $related_phids = array(
      $project->getPHID(),
      $xaction->getAuthorPHID(),
    );

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_PROJECT)
      ->setStoryData(
        array(
          'projectPHID'   => $project->getPHID(),
          'transactionID' => $xaction->getID(),
          'type'          => $xaction->getTransactionType(),
          'old'           => $xaction->getOldValue(),
          'new'           => $xaction->getNewValue(),
        ))
      ->setStoryTime(time())
      ->setStoryAuthorPHID($xaction->getAuthorPHID())
      ->setRelatedPHIDs($related_phids)
      ->publish();
  }

  private function transactionHasEffect(
    PhabricatorProjectTransaction $xaction) {
    return ($xaction->getOldValue() !== $xaction->getNewValue());
  }

}
