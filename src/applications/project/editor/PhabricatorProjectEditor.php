<?php

final class PhabricatorProjectEditor extends PhabricatorEditor {

  private $project;
  private $projectName;

  private $addEdges = array();
  private $remEdges = array();

  private $shouldArchive = false;

  private function setShouldArchive($should_archive) {
    $this->shouldArchive = $should_archive;
    return $this;
  }
  private function shouldArchive() {
    return $this->shouldArchive;
  }

  public static function applyJoinProject(
    PhabricatorProject $project,
    PhabricatorUser $user) {

    $members = $project->getMemberPHIDs();
    $members[] = $user->getPHID();

    self::applyOneTransaction(
      $project,
      $user,
      PhabricatorProjectTransaction::TYPE_MEMBERS,
      $members);
  }

  public static function applyLeaveProject(
    PhabricatorProject $project,
    PhabricatorUser $user) {

    $members = array_fill_keys($project->getMemberPHIDs(), true);
    unset($members[$user->getPHID()]);
    $members = array_keys($members);

    self::applyOneTransaction(
      $project,
      $user,
      PhabricatorProjectTransaction::TYPE_MEMBERS,
      $members);
  }

  private static function applyOneTransaction(
    PhabricatorProject $project,
    PhabricatorUser $user,
    $type,
    $new_value) {

    $xaction = new PhabricatorProjectTransaction();
    $xaction->setTransactionType($type);
    $xaction->setNewValue($new_value);

    $editor = new PhabricatorProjectEditor($project);
    $editor->setActor($user);
    $editor->applyTransactions(array($xaction));
  }


  public function __construct(PhabricatorProject $project) {
    $this->project = $project;
  }

  public function applyTransactions(array $transactions) {
    assert_instances_of($transactions, 'PhabricatorProjectTransaction');
    $actor = $this->requireActor();

    $project = $this->project;

    $is_new = !$project->getID();

    if ($is_new) {
      $project->setAuthorPHID($actor->getPHID());
    }

    foreach ($transactions as $key => $xaction) {
      $this->setTransactionOldValue($project, $xaction);
      if (!$this->transactionHasEffect($xaction)) {
        unset($transactions[$key]);
        continue;
      }
    }

    if (!$is_new) {
      // You must be able to view a project in order to edit it in any capacity.
      PhabricatorPolicyFilter::requireCapability(
        $actor,
        $project,
        PhabricatorPolicyCapability::CAN_VIEW);

      $need_edit = false;
      $need_join = false;
      foreach ($transactions as $key => $xaction) {
        if ($this->getTransactionRequiresEditCapability($xaction)) {
          $need_edit = true;
        }
        if ($this->getTransactionRequiresJoinCapability($xaction)) {
          $need_join = true;
        }
      }

      if ($need_edit) {
        PhabricatorPolicyFilter::requireCapability(
          $actor,
          $project,
          PhabricatorPolicyCapability::CAN_EDIT);
      }

      if ($need_join) {
        PhabricatorPolicyFilter::requireCapability(
          $actor,
          $project,
          PhabricatorPolicyCapability::CAN_JOIN);
      }
    }

    if (!$transactions) {
      return $this;
    }

    foreach ($transactions as $xaction) {
      $this->applyTransactionEffect($project, $xaction);
    }

    try {
      $project->openTransaction();

        if ($this->shouldArchive()) {
          $project->setStatus(PhabricatorProjectStatus::STATUS_ARCHIVED);
        }
        $project->save();

        $edge_type = PhabricatorEdgeConfig::TYPE_PROJ_MEMBER;
        $editor = new PhabricatorEdgeEditor();
        $editor->setActor($actor);
        foreach ($this->remEdges as $phid) {
          $editor->removeEdge($project->getPHID(), $edge_type, $phid);
        }
        foreach ($this->addEdges as $phid) {
          $editor->addEdge($project->getPHID(), $edge_type, $phid);
        }
        $editor->save();

        foreach ($transactions as $xaction) {
          $xaction->setAuthorPHID($actor->getPHID());
          $xaction->setObjectPHID($project->getPHID());
          $xaction->setViewPolicy('public');
          $xaction->setEditPolicy($actor->getPHID());
          $xaction->setContentSource(
            PhabricatorContentSource::newForSource(
              PhabricatorContentSource::SOURCE_LEGACY,
              array()));
          $xaction->save();
        }
      $project->saveTransaction();
    } catch (AphrontQueryDuplicateKeyException $ex) {
      // We already validated the slug, but might race. Try again to see if
      // that's the issue. If it is, we'll throw a more specific exception. If
      // not, throw the original exception.
      $this->validateName($project);
      throw $ex;
    }

    id(new PhabricatorSearchIndexer())
      ->indexDocumentByPHID($project->getPHID());

    return $this;
  }

  private function validateName(PhabricatorProject $project) {
    $slug = $project->getPhrictionSlug();
    $name = $project->getName();

    if ($slug == '/') {
      throw new PhabricatorProjectNameCollisionException(
        pht("Project names must be unique and contain some ".
        "letters or numbers."));
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
        pht("Project names must be unique. The name '%s' is too similar to ".
        "the name of another project, '%s' (Project ID: ".
        "%d). Choose a unique name.", $name, $other_name, $other_id));
    }
  }

  private function setTransactionOldValue(
    PhabricatorProject $project,
    PhabricatorProjectTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        $xaction->setOldValue($project->getName());
        break;
      case PhabricatorProjectTransaction::TYPE_STATUS:
        $xaction->setOldValue($project->getStatus());
        break;
      case PhabricatorProjectTransaction::TYPE_MEMBERS:
        $member_phids = $project->getMemberPHIDs();

        $old_value = array_values($member_phids);
        $xaction->setOldValue($old_value);

        $new_value = $xaction->getNewValue();
        $new_value = array_filter($new_value);
        $new_value = array_unique($new_value);
        $new_value = array_values($new_value);
        $xaction->setNewValue($new_value);
        break;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $xaction->setOldValue($project->getViewPolicy());
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $xaction->setOldValue($project->getEditPolicy());
        break;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        $xaction->setOldValue($project->getJoinPolicy());
        break;
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }
    return $this;
  }

  private function applyTransactionEffect(
    PhabricatorProject $project,
    PhabricatorProjectTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        $old_slug = $project->getFullPhrictionSlug();
        $project->setName($xaction->getNewValue());
        $project->setPhrictionSlug($xaction->getNewValue());

        if ($xaction->getOldValue()) {
          $old_document = id(new PhrictionDocument())
            ->loadOneWhere(
              'slug = %s',
              $old_slug);
          if ($old_document && $old_document->getStatus() ==
              PhrictionDocumentStatus::STATUS_EXISTS) {
            $content = id(new PhrictionContent())
              ->load($old_document->getContentID());
            $from_editor = id(PhrictionDocumentEditor::newForSlug($old_slug))
              ->setActor($this->getActor())
              ->setTitle($content->getTitle())
              ->setContent($content->getContent())
              ->setDescription($content->getDescription());

            $target_editor = id(PhrictionDocumentEditor::newForSlug(
              $project->getFullPhrictionSlug()))
              ->setActor($this->getActor())
              ->setTitle($content->getTitle())
              ->setContent($content->getContent())
              ->setDescription($content->getDescription())
              ->moveHere($old_document->getID(), $old_document->getPHID());

            $target_document = $target_editor->getDocument();
            $from_editor->moveAway($target_document->getID());
          }
        }
        $this->validateName($project);
        break;
      case PhabricatorProjectTransaction::TYPE_STATUS:
        $project->setStatus($xaction->getNewValue());
        break;
      case PhabricatorProjectTransaction::TYPE_MEMBERS:
        $old = array_fill_keys($xaction->getOldValue(), true);
        $new = array_fill_keys($xaction->getNewValue(), true);
        $this->addEdges = array_keys(array_diff_key($new, $old));
        $this->remEdges = array_keys(array_diff_key($old, $new));
        if ($new === array()) {
          $this->setShouldArchive(true);
        }
        break;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $project->setViewPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $project->setEditPolicy($xaction->getNewValue());

        // You can't edit away your ability to edit the project.
        PhabricatorPolicyFilter::mustRetainCapability(
          $this->getActor(),
          $project,
          PhabricatorPolicyCapability::CAN_EDIT);
        break;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        $project->setJoinPolicy($xaction->getNewValue());
        break;
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }
  }

  private function transactionHasEffect(
    PhabricatorProjectTransaction $xaction) {
    return ($xaction->getOldValue() !== $xaction->getNewValue());
  }


  /**
   * All transactions except joining or leaving a project require edit
   * capability.
   */
  private function getTransactionRequiresEditCapability(
    PhabricatorProjectTransaction $xaction) {
    return ($this->isJoinOrLeaveTransaction($xaction) === null);
  }


  /**
   * Joining a project requires the join capability. Anyone leave a project.
   */
  private function getTransactionRequiresJoinCapability(
    PhabricatorProjectTransaction $xaction) {
    $type = $this->isJoinOrLeaveTransaction($xaction);
    return ($type == 'join');
  }


  /**
   * Returns 'join' if this transaction causes the acting user ONLY to join the
   * project.
   *
   * Returns 'leave' if this transaction causes the acting user ONLY to leave
   * the project.
   *
   * Returns null in all other cases.
   */
  private function isJoinOrLeaveTransaction(
    PhabricatorProjectTransaction $xaction) {

    $type = $xaction->getTransactionType();
    if ($type != PhabricatorProjectTransaction::TYPE_MEMBERS) {
      return null;
    }

    switch ($type) {
      case PhabricatorProjectTransaction::TYPE_MEMBERS:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();

        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if (count($add) > 1) {
          return null;
        } else if (count($add) == 1) {
          if (reset($add) != $this->getActor()->getPHID()) {
            return null;
          } else {
            return 'join';
          }
        }

        if (count($rem) > 1) {
          return null;
        } else if (count($rem) == 1) {
          if (reset($rem) != $this->getActor()->getPHID()) {
            return null;
          } else {
            return 'leave';
          }
        }
        break;
    }

    return true;
  }

}
