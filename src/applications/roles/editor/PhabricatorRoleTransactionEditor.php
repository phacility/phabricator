<?php

final class PhabricatorRoleTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  private $isMilestone;

  private function setIsMilestone($is_milestone) {
    $this->isMilestone = $is_milestone;
    return $this;
  }

  public function getIsMilestone() {
    return $this->isMilestone;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Roles');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this role.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_JOIN_POLICY;

    return $types;
  }

  protected function validateAllTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $errors = array();

    // Prevent creating roles which are both subroles and milestones,
    // since this does not make sense, won't work, and will break everything.
    $parent_xaction = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorRoleParentTransaction::TRANSACTIONTYPE:
        case PhabricatorRoleMilestoneTransaction::TRANSACTIONTYPE:
          if ($xaction->getNewValue() === null) {
            continue 2;
          }

          if (!$parent_xaction) {
            $parent_xaction = $xaction;
            continue 2;
          }

          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $xaction->getTransactionType(),
            pht('Invalid'),
            pht(
              'When creating a role, specify a maximum of one parent '.
              'role or milestone role. A role can not be both a '.
              'subrole and a milestone.'),
            $xaction);
          break 2;
      }
    }

    $is_milestone = $this->getIsMilestone();

    $is_parent = $object->getHasSubroles();

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_EDGE:
          $type = $xaction->getMetadataValue('edge:type');
          if ($type != PhabricatorRoleRoleHasMemberEdgeType::EDGECONST) {
            break;
          }

          if ($is_parent) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $xaction->getTransactionType(),
              pht('Invalid'),
              pht(
                'You can not change members of a role with subroles '.
                'directly. Members of any subrole are automatically '.
                'members of the parent role.'),
              $xaction);
          }

          if ($is_milestone) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $xaction->getTransactionType(),
              pht('Invalid'),
              pht(
                'You can not change members of a milestone. Members of the '.
                'parent role are automatically members of the milestone.'),
              $xaction);
          }
          break;
      }
    }

    return $errors;
  }

  protected function willPublish(PhabricatorLiskDAO $object, array $xactions) {
    // NOTE: We're using the omnipotent user here because the original actor
    // may no longer have permission to view the object.
    return id(new PhabricatorRoleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($object->getPHID()))
      ->needAncestorMembers(true)
      ->executeOne();
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Role]');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $this->getActingAsPHID(),
    );
  }

  protected function getMailCc(PhabricatorLiskDAO $object) {
    return array();
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorRoleTransaction::MAILTAG_METADATA =>
        pht('Role name, hashtags, icon, image, or color changes.'),
      PhabricatorRoleTransaction::MAILTAG_MEMBERS =>
        pht('Role membership changes.'),
      PhabricatorRoleTransaction::MAILTAG_WATCHERS =>
        pht('Role watcher list changes.'),
      PhabricatorRoleTransaction::MAILTAG_OTHER =>
        pht('Other role activity not listed above occurs.'),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new RoleReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("{$name}");
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $uri = '/role/profile/'.$object->getID().'/';
    $body->addLinkSection(
      pht('ROLE DETAIL'),
      PhabricatorEnv::getProductionURI($uri));

    return $body;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $materialize = false;
    $new_parent = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_EDGE:
          switch ($xaction->getMetadataValue('edge:type')) {
            case PhabricatorRoleRoleHasMemberEdgeType::EDGECONST:
              $materialize = true;
              break;
          }
          break;
        case PhabricatorRoleParentTransaction::TRANSACTIONTYPE:
        case PhabricatorRoleMilestoneTransaction::TRANSACTIONTYPE:
          $materialize = true;
          $new_parent = $object->getParentRole();
          break;
      }
    }

    if ($new_parent) {
      // If we just created the first subrole of this parent, we want to
      // copy all of the real members to the subrole.
      if (!$new_parent->getHasSubroles()) {
        $member_type = PhabricatorRoleRoleHasMemberEdgeType::EDGECONST;

        $role_members = PhabricatorEdgeQuery::loadDestinationPHIDs(
          $new_parent->getPHID(),
          $member_type);

        if ($role_members) {
          $editor = id(new PhabricatorEdgeEditor());
          foreach ($role_members as $phid) {
            $editor->addEdge($object->getPHID(), $member_type, $phid);
          }
          $editor->save();
        }
      }
    }

    // TODO: We should dump an informational transaction onto the parent
    // role to show that we created the sub-thing.

    if ($materialize) {
      id(new PhabricatorRolesMembershipIndexEngineExtension())
        ->rematerialize($object);
    }

    if ($new_parent) {
      id(new PhabricatorRolesMembershipIndexEngineExtension())
        ->rematerialize($new_parent);
    }

    // See PHI1046. Milestones are always in the Space of their parent role.
    // Synchronize the database values to match the application values.
    $conn = $object->establishConnection('w');
    queryfx(
      $conn,
      'UPDATE %R SET spacePHID = %ns
        WHERE parentRolePHID = %s AND milestoneNumber IS NOT NULL',
      $object,
      $object->getSpacePHID(),
      $object->getPHID());

    return parent::applyFinalEffects($object, $xactions);
  }

  public function addSlug(PhabricatorRole $role, $slug, $force) {
    $slug = PhabricatorSlug::normalizeProjectSlug($slug);
    $table = new PhabricatorRoleSlug();
    $role_phid = $role->getPHID();

    if ($force) {
      // If we have the `$force` flag set, we only want to ignore an existing
      // slug if it's for the same role. We'll error on collisions with
      // other roles.
      $current = $table->loadOneWhere(
        'slug = %s AND rolePHID = %s',
        $slug,
        $role_phid);
    } else {
      // Without the `$force` flag, we'll just return without doing anything
      // if any other role already has the slug.
      $current = $table->loadOneWhere(
        'slug = %s',
        $slug);
    }

    if ($current) {
      return;
    }

    return id(new PhabricatorRoleSlug())
      ->setSlug($slug)
      ->setRolePHID($role_phid)
      ->save();
  }

  public function removeSlugs(PhabricatorRole $role, array $slugs) {
    if (!$slugs) {
      return;
    }

    // We're going to try to delete both the literal and normalized versions
    // of all slugs. This allows us to destroy old slugs that are no longer
    // valid.
    foreach ($this->normalizeSlugs($slugs) as $slug) {
      $slugs[] = $slug;
    }

    $objects = id(new PhabricatorRoleSlug())->loadAllWhere(
      'rolePHID = %s AND slug IN (%Ls)',
      $role->getPHID(),
      $slugs);

    foreach ($objects as $object) {
      $object->delete();
    }
  }

  public function normalizeSlugs(array $slugs) {
    foreach ($slugs as $key => $slug) {
      $slugs[$key] = PhabricatorSlug::normalizeProjectSlug($slug);
    }

    $slugs = array_unique($slugs);
    $slugs = array_values($slugs);

    return $slugs;
  }

  protected function adjustObjectForPolicyChecks(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $copy = parent::adjustObjectForPolicyChecks($object, $xactions);

    $type_edge = PhabricatorTransactions::TYPE_EDGE;
    $edgetype_member = PhabricatorRoleRoleHasMemberEdgeType::EDGECONST;

    // See T13462. If we're creating a milestone, set a dummy milestone
    // number so the role behaves like a milestone and uses milestone
    // policy rules. Otherwise, we'll end up checking the default policies
    // (which are not relevant to milestones) instead of the parent role
    // policies (which are the correct policies).
    if ($this->getIsMilestone() && !$copy->isMilestone()) {
      $copy->setMilestoneNumber(1);
    }

    $hint = null;
    if ($this->getIsMilestone()) {
      // See T13462. If we're creating a milestone, predict that the members
      // of the newly created milestone will be the same as the members of the
      // parent role, since this is the governing rule.

      $parent = $copy->getParentRole();

      $parent = id(new PhabricatorRoleQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($parent->getPHID()))
        ->needMembers(true)
        ->executeOne();
      $members = $parent->getMemberPHIDs();

      $hint = array_fuse($members);
    } else {
      $member_xaction = null;
      foreach ($xactions as $xaction) {
        if ($xaction->getTransactionType() !== $type_edge) {
          continue;
        }

        $edgetype = $xaction->getMetadataValue('edge:type');
        if ($edgetype !== $edgetype_member) {
          continue;
        }

        $member_xaction = $xaction;
      }

      if ($member_xaction) {
        $object_phid = $object->getPHID();

        if ($object_phid) {
          $role = id(new PhabricatorRoleQuery())
            ->setViewer($this->getActor())
            ->withPHIDs(array($object_phid))
            ->needMembers(true)
            ->executeOne();
          $members = $role->getMemberPHIDs();
        } else {
          $members = array();
        }

        $clone_xaction = clone $member_xaction;
        $hint = $this->getPHIDTransactionNewValue($clone_xaction, $members);
        $hint = array_fuse($hint);
      }
    }

    if ($hint !== null) {
      $rule = new PhabricatorRoleMembersPolicyRule();
      PhabricatorPolicyRule::passTransactionHintToRule(
        $copy,
        $rule,
        $hint);
    }

    return $copy;
  }

  protected function expandTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $actor = $this->getActor();
    $actor_phid = $actor->getPHID();

    $results = parent::expandTransactions($object, $xactions);

    $is_milestone = $object->isMilestone();
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorRoleMilestoneTransaction::TRANSACTIONTYPE:
          if ($xaction->getNewValue() !== null) {
            $is_milestone = true;
          }
          break;
      }
    }

    $this->setIsMilestone($is_milestone);

    return $results;
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // Herald rules may run on behalf of other users and need to execute
    // membership checks against ancestors.
    $role = id(new PhabricatorRoleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($object->getPHID()))
      ->needAncestorMembers(true)
      ->executeOne();

    return id(new PhabricatorRoleHeraldAdapter())
      ->setRole($role);
  }

}
