<?php

final class PhabricatorProjectTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  private $isMilestone;

  private function setIsMilestone($is_milestone) {
    $this->isMilestone = $is_milestone;
    return $this;
  }

  private function getIsMilestone() {
    return $this->isMilestone;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Projects');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_JOIN_POLICY;

    $types[] = PhabricatorProjectTransaction::TYPE_NAME;
    $types[] = PhabricatorProjectTransaction::TYPE_SLUGS;
    $types[] = PhabricatorProjectTransaction::TYPE_STATUS;
    $types[] = PhabricatorProjectTransaction::TYPE_IMAGE;
    $types[] = PhabricatorProjectTransaction::TYPE_ICON;
    $types[] = PhabricatorProjectTransaction::TYPE_COLOR;
    $types[] = PhabricatorProjectTransaction::TYPE_LOCKED;
    $types[] = PhabricatorProjectTransaction::TYPE_PARENT;
    $types[] = PhabricatorProjectTransaction::TYPE_MILESTONE;
    $types[] = PhabricatorProjectTransaction::TYPE_HASWORKBOARD;
    $types[] = PhabricatorProjectTransaction::TYPE_DEFAULT_SORT;
    $types[] = PhabricatorProjectTransaction::TYPE_DEFAULT_FILTER;
    $types[] = PhabricatorProjectTransaction::TYPE_BACKGROUND;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorProjectTransaction::TYPE_SLUGS:
        $slugs = $object->getSlugs();
        $slugs = mpull($slugs, 'getSlug', 'getSlug');
        unset($slugs[$object->getPrimarySlug()]);
        return array_keys($slugs);
      case PhabricatorProjectTransaction::TYPE_STATUS:
        return $object->getStatus();
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        return $object->getProfileImagePHID();
      case PhabricatorProjectTransaction::TYPE_ICON:
        return $object->getIcon();
      case PhabricatorProjectTransaction::TYPE_COLOR:
        return $object->getColor();
      case PhabricatorProjectTransaction::TYPE_LOCKED:
        return (int)$object->getIsMembershipLocked();
      case PhabricatorProjectTransaction::TYPE_HASWORKBOARD:
        return (int)$object->getHasWorkboard();
      case PhabricatorProjectTransaction::TYPE_PARENT:
      case PhabricatorProjectTransaction::TYPE_MILESTONE:
        return null;
      case PhabricatorProjectTransaction::TYPE_DEFAULT_SORT:
        return $object->getDefaultWorkboardSort();
      case PhabricatorProjectTransaction::TYPE_DEFAULT_FILTER:
        return $object->getDefaultWorkboardFilter();
      case PhabricatorProjectTransaction::TYPE_BACKGROUND:
        return $object->getWorkboardBackgroundColor();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
      case PhabricatorProjectTransaction::TYPE_STATUS:
      case PhabricatorProjectTransaction::TYPE_IMAGE:
      case PhabricatorProjectTransaction::TYPE_ICON:
      case PhabricatorProjectTransaction::TYPE_COLOR:
      case PhabricatorProjectTransaction::TYPE_LOCKED:
      case PhabricatorProjectTransaction::TYPE_PARENT:
      case PhabricatorProjectTransaction::TYPE_MILESTONE:
      case PhabricatorProjectTransaction::TYPE_DEFAULT_SORT:
      case PhabricatorProjectTransaction::TYPE_DEFAULT_FILTER:
        return $xaction->getNewValue();
      case PhabricatorProjectTransaction::TYPE_HASWORKBOARD:
        return (int)$xaction->getNewValue();
      case PhabricatorProjectTransaction::TYPE_BACKGROUND:
        $value = $xaction->getNewValue();
        if (!strlen($value)) {
          return null;
        }
        return $value;
      case PhabricatorProjectTransaction::TYPE_SLUGS:
        return $this->normalizeSlugs($xaction->getNewValue());
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        $name = $xaction->getNewValue();
        $object->setName($name);
        if (!$this->getIsMilestone()) {
          $object->setPrimarySlug(PhabricatorSlug::normalizeProjectSlug($name));
        }
        return;
      case PhabricatorProjectTransaction::TYPE_SLUGS:
        return;
      case PhabricatorProjectTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        $object->setProfileImagePHID($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_ICON:
        $object->setIcon($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_COLOR:
        $object->setColor($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_LOCKED:
        $object->setIsMembershipLocked($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_PARENT:
        $object->setParentProjectPHID($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_MILESTONE:
        $number = $object->getParentProject()->loadNextMilestoneNumber();
        $object->setMilestoneNumber($number);
        $object->setParentProjectPHID($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_HASWORKBOARD:
        $object->setHasWorkboard($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_DEFAULT_SORT:
        $object->setDefaultWorkboardSort($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_DEFAULT_FILTER:
        $object->setDefaultWorkboardFilter($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_BACKGROUND:
        $object->setWorkboardBackgroundColor($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        // First, add the old name as a secondary slug; this is helpful
        // for renames and generally a good thing to do.
        if (!$this->getIsMilestone()) {
          if ($old !== null) {
            $this->addSlug($object, $old, false);
          }
          $this->addSlug($object, $new, false);
        }
        return;
      case PhabricatorProjectTransaction::TYPE_SLUGS:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        foreach ($add as $slug) {
          $this->addSlug($object, $slug, true);
        }

        $this->removeSlugs($object, $rem);
        return;
      case PhabricatorProjectTransaction::TYPE_STATUS:
      case PhabricatorProjectTransaction::TYPE_IMAGE:
      case PhabricatorProjectTransaction::TYPE_ICON:
      case PhabricatorProjectTransaction::TYPE_COLOR:
      case PhabricatorProjectTransaction::TYPE_LOCKED:
      case PhabricatorProjectTransaction::TYPE_PARENT:
      case PhabricatorProjectTransaction::TYPE_MILESTONE:
      case PhabricatorProjectTransaction::TYPE_HASWORKBOARD:
      case PhabricatorProjectTransaction::TYPE_DEFAULT_SORT:
      case PhabricatorProjectTransaction::TYPE_DEFAULT_FILTER:
      case PhabricatorProjectTransaction::TYPE_BACKGROUND:
        return;
     }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateAllTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $errors = array();

    // Prevent creating projects which are both subprojects and milestones,
    // since this does not make sense, won't work, and will break everything.
    $parent_xaction = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorProjectTransaction::TYPE_PARENT:
        case PhabricatorProjectTransaction::TYPE_MILESTONE:
          if ($xaction->getNewValue() === null) {
            continue;
          }

          if (!$parent_xaction) {
            $parent_xaction = $xaction;
            continue;
          }

          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $xaction->getTransactionType(),
            pht('Invalid'),
            pht(
              'When creating a project, specify a maximum of one parent '.
              'project or milestone project. A project can not be both a '.
              'subproject and a milestone.'),
            $xaction);
          break;
          break;
      }
    }

    $is_milestone = $this->getIsMilestone();

    $is_parent = $object->getHasSubprojects();

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_EDGE:
          $type = $xaction->getMetadataValue('edge:type');
          if ($type != PhabricatorProjectProjectHasMemberEdgeType::EDGECONST) {
            break;
          }

          if ($is_parent) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $xaction->getTransactionType(),
              pht('Invalid'),
              pht(
                'You can not change members of a project with subprojects '.
                'directly. Members of any subproject are automatically '.
                'members of the parent project.'),
              $xaction);
          }

          if ($is_milestone) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $xaction->getTransactionType(),
              pht('Invalid'),
              pht(
                'You can not change members of a milestone. Members of the '.
                'parent project are automatically members of the milestone.'),
              $xaction);
          }
          break;
      }
    }

    return $errors;
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Project name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }

        if (!$xactions) {
          break;
        }

        if ($this->getIsMilestone()) {
          break;
        }

        $name = last($xactions)->getNewValue();

        if (!PhabricatorSlug::isValidProjectSlug($name)) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'Project names must contain at least one letter or number.'),
            last($xactions));
          break;
        }

        $slug = PhabricatorSlug::normalizeProjectSlug($name);

        $slug_used_already = id(new PhabricatorProjectSlug())
          ->loadOneWhere('slug = %s', $slug);
        if ($slug_used_already &&
            $slug_used_already->getProjectPHID() != $object->getPHID()) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Duplicate'),
            pht(
              'Project name generates the same hashtag ("%s") as another '.
              'existing project. Choose a unique name.',
              '#'.$slug),
            nonempty(last($xactions), null));
          $errors[] = $error;
        }
        break;
      case PhabricatorProjectTransaction::TYPE_SLUGS:
        if (!$xactions) {
          break;
        }

        $slug_xaction = last($xactions);

        $new = $slug_xaction->getNewValue();

        $invalid = array();
        foreach ($new as $slug) {
          if (!PhabricatorSlug::isValidProjectSlug($slug)) {
            $invalid[] = $slug;
          }
        }

        if ($invalid) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'Hashtags must contain at least one letter or number. %s '.
              'project hashtag(s) are invalid: %s.',
              phutil_count($invalid),
              implode(', ', $invalid)),
            $slug_xaction);
          break;
        }

        $new = $this->normalizeSlugs($new);

        if ($new) {
          $slugs_used_already = id(new PhabricatorProjectSlug())
            ->loadAllWhere('slug IN (%Ls)', $new);
        } else {
          // The project doesn't have any extra slugs.
          $slugs_used_already = array();
        }

        $slugs_used_already = mgroup($slugs_used_already, 'getProjectPHID');
        foreach ($slugs_used_already as $project_phid => $used_slugs) {
          if ($project_phid == $object->getPHID()) {
            continue;
          }

          $used_slug_strs = mpull($used_slugs, 'getSlug');

          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              '%s project hashtag(s) are already used by other projects: %s.',
              phutil_count($used_slug_strs),
              implode(', ', $used_slug_strs)),
            $slug_xaction);
          $errors[] = $error;
        }

        break;
      case PhabricatorProjectTransaction::TYPE_PARENT:
      case PhabricatorProjectTransaction::TYPE_MILESTONE:
        if (!$xactions) {
          break;
        }

        $xaction = last($xactions);

        $parent_phid = $xaction->getNewValue();
        if (!$parent_phid) {
          continue;
        }

        if (!$this->getIsNewObject()) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'You can only set a parent or milestone project when creating a '.
              'project for the first time.'),
            $xaction);
          break;
        }

        $projects = id(new PhabricatorProjectQuery())
          ->setViewer($this->requireActor())
          ->withPHIDs(array($parent_phid))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
              PhabricatorPolicyCapability::CAN_EDIT,
            ))
          ->execute();
        if (!$projects) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'Parent or milestone project PHID ("%s") must be the PHID of a '.
              'valid, visible project which you have permission to edit.',
              $parent_phid),
            $xaction);
          break;
        }

        $project = head($projects);

        if ($project->isMilestone()) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'Parent or milestone project PHID ("%s") must not be a '.
              'milestone. Milestones may not have subprojects or milestones.',
              $parent_phid),
            $xaction);
          break;
        }

        $limit = PhabricatorProject::getProjectDepthLimit();
        if ($project->getProjectDepth() >= ($limit - 1)) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'You can not create a subproject or mielstone under this parent '.
              'because it would nest projects too deeply. The maximum '.
              'nesting depth of projects is %s.',
              new PhutilNumber($limit)),
            $xaction);
          break;
        }

        $object->attachParentProject($project);
        break;
    }

    return $errors;
  }


  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
      case PhabricatorProjectTransaction::TYPE_STATUS:
      case PhabricatorProjectTransaction::TYPE_IMAGE:
      case PhabricatorProjectTransaction::TYPE_ICON:
      case PhabricatorProjectTransaction::TYPE_COLOR:
        PhabricatorPolicyFilter::requireCapability(
          $this->requireActor(),
          $object,
          PhabricatorPolicyCapability::CAN_EDIT);
        return;
      case PhabricatorProjectTransaction::TYPE_LOCKED:
        PhabricatorPolicyFilter::requireCapability(
          $this->requireActor(),
          newv($this->getEditorApplicationClass(), array()),
          ProjectCanLockProjectsCapability::CAPABILITY);
        return;
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($xaction->getMetadataValue('edge:type')) {
          case PhabricatorProjectProjectHasMemberEdgeType::EDGECONST:
            $old = $xaction->getOldValue();
            $new = $xaction->getNewValue();

            $add = array_keys(array_diff_key($new, $old));
            $rem = array_keys(array_diff_key($old, $new));

            $actor_phid = $this->requireActor()->getPHID();

            $is_join = (($add === array($actor_phid)) && !$rem);
            $is_leave = (($rem === array($actor_phid)) && !$add);

            if ($is_join) {
              // You need CAN_JOIN to join a project.
              PhabricatorPolicyFilter::requireCapability(
                $this->requireActor(),
                $object,
                PhabricatorPolicyCapability::CAN_JOIN);
            } else if ($is_leave) {
              // You usually don't need any capabilities to leave a project.
              if ($object->getIsMembershipLocked()) {
                // you must be able to edit though to leave locked projects
                PhabricatorPolicyFilter::requireCapability(
                  $this->requireActor(),
                  $object,
                  PhabricatorPolicyCapability::CAN_EDIT);
              }
            } else {
              // You need CAN_EDIT to change members other than yourself.
              PhabricatorPolicyFilter::requireCapability(
                $this->requireActor(),
                $object,
                PhabricatorPolicyCapability::CAN_EDIT);
            }
            return;
        }
        break;
    }

    return parent::requireCapabilities($object, $xaction);
  }

  protected function willPublish(PhabricatorLiskDAO $object, array $xactions) {
    // NOTE: We're using the omnipotent user here because the original actor
    // may no longer have permission to view the object.
    return id(new PhabricatorProjectQuery())
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
    return pht('[Project]');
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
      PhabricatorProjectTransaction::MAILTAG_METADATA =>
        pht('Project name, hashtags, icon, image, or color changes.'),
      PhabricatorProjectTransaction::MAILTAG_MEMBERS =>
        pht('Project membership changes.'),
      PhabricatorProjectTransaction::MAILTAG_WATCHERS =>
        pht('Project watcher list changes.'),
      PhabricatorProjectTransaction::MAILTAG_OTHER =>
        pht('Other project activity not listed above occurs.'),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new ProjectReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("{$name}")
      ->addHeader('Thread-Topic', "Project {$id}");
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $uri = '/project/profile/'.$object->getID().'/';
    $body->addLinkSection(
      pht('PROJECT DETAIL'),
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

  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        $new = $xaction->getNewValue();
        if ($new) {
          return array($new);
        }
        break;
    }

    return parent::extractFilePHIDsFromCustomTransaction($object, $xaction);
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
            case PhabricatorProjectProjectHasMemberEdgeType::EDGECONST:
              $materialize = true;
              break;
          }
          break;
        case PhabricatorProjectTransaction::TYPE_PARENT:
        case PhabricatorProjectTransaction::TYPE_MILESTONE:
          $materialize = true;
          $new_parent = $object->getParentProject();
          break;
      }
    }

    if ($new_parent) {
      // If we just created the first subproject of this parent, we want to
      // copy all of the real members to the subproject.
      if (!$new_parent->getHasSubprojects()) {
        $member_type = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

        $project_members = PhabricatorEdgeQuery::loadDestinationPHIDs(
          $new_parent->getPHID(),
          $member_type);

        if ($project_members) {
          $editor = id(new PhabricatorEdgeEditor());
          foreach ($project_members as $phid) {
            $editor->addEdge($object->getPHID(), $member_type, $phid);
          }
          $editor->save();
        }
      }
    }

    if ($this->getIsNewObject()) {
      $this->setDefaultProfilePicture($object);
    }

    // TODO: We should dump an informational transaction onto the parent
    // project to show that we created the sub-thing.

    if ($materialize) {
      id(new PhabricatorProjectsMembershipIndexEngineExtension())
        ->rematerialize($object);
    }

    if ($new_parent) {
      id(new PhabricatorProjectsMembershipIndexEngineExtension())
        ->rematerialize($new_parent);
    }

    return parent::applyFinalEffects($object, $xactions);
  }

  private function addSlug(PhabricatorProject $project, $slug, $force) {
    $slug = PhabricatorSlug::normalizeProjectSlug($slug);
    $table = new PhabricatorProjectSlug();
    $project_phid = $project->getPHID();

    if ($force) {
      // If we have the `$force` flag set, we only want to ignore an existing
      // slug if it's for the same project. We'll error on collisions with
      // other projects.
      $current = $table->loadOneWhere(
        'slug = %s AND projectPHID = %s',
        $slug,
        $project_phid);
    } else {
      // Without the `$force` flag, we'll just return without doing anything
      // if any other project already has the slug.
      $current = $table->loadOneWhere(
        'slug = %s',
        $slug);
    }

    if ($current) {
      return;
    }

    return id(new PhabricatorProjectSlug())
      ->setSlug($slug)
      ->setProjectPHID($project_phid)
      ->save();
  }

  private function removeSlugs(PhabricatorProject $project, array $slugs) {
    if (!$slugs) {
      return;
    }

    // We're going to try to delete both the literal and normalized versions
    // of all slugs. This allows us to destroy old slugs that are no longer
    // valid.
    foreach ($this->normalizeSlugs($slugs) as $slug) {
      $slugs[] = $slug;
    }

    $objects = id(new PhabricatorProjectSlug())->loadAllWhere(
      'projectPHID = %s AND slug IN (%Ls)',
      $project->getPHID(),
      $slugs);

    foreach ($objects as $object) {
      $object->delete();
    }
  }

  private function normalizeSlugs(array $slugs) {
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
    $edgetype_member = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

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
        $project = id(new PhabricatorProjectQuery())
          ->setViewer($this->getActor())
          ->withPHIDs(array($object_phid))
          ->needMembers(true)
          ->executeOne();
        $members = $project->getMemberPHIDs();
      } else {
        $members = array();
      }

      $clone_xaction = clone $member_xaction;
      $hint = $this->getPHIDTransactionNewValue($clone_xaction, $members);
      $rule = new PhabricatorProjectMembersPolicyRule();

      $hint = array_fuse($hint);

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
        case PhabricatorProjectTransaction::TYPE_MILESTONE:
          if ($xaction->getNewValue() !== null) {
            $is_milestone = true;
          }
          break;
      }
    }

    $this->setIsMilestone($is_milestone);

    return $results;
  }

  private function setDefaultProfilePicture(PhabricatorProject $project) {
    if ($project->isMilestone()) {
      return;
    }

    $compose_color = $project->getDisplayIconComposeColor();
    $compose_icon = $project->getDisplayIconComposeIcon();

    $builtin = id(new PhabricatorFilesComposeIconBuiltinFile())
      ->setColor($compose_color)
      ->setIcon($compose_icon);

    $data = $builtin->loadBuiltinFileData();

    $file = PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => $builtin->getBuiltinDisplayName(),
        'profile' => true,
        'canCDN' => true,
      ));

    $project
      ->setProfileImagePHID($file->getPHID())
      ->save();
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
    $project = id(new PhabricatorProjectQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($object->getPHID()))
      ->needAncestorMembers(true)
      ->executeOne();

    return id(new PhabricatorProjectHeraldAdapter())
      ->setProject($project);
  }

}
