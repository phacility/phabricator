<?php

final class PhabricatorProjectTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

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
        return $slugs;
      case PhabricatorProjectTransaction::TYPE_STATUS:
        return $object->getStatus();
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        return $object->getProfileImagePHID();
      case PhabricatorProjectTransaction::TYPE_ICON:
        return $object->getIcon();
      case PhabricatorProjectTransaction::TYPE_COLOR:
        return $object->getColor();
      case PhabricatorProjectTransaction::TYPE_LOCKED:
        return (int) $object->getIsMembershipLocked();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
      case PhabricatorProjectTransaction::TYPE_SLUGS:
      case PhabricatorProjectTransaction::TYPE_STATUS:
      case PhabricatorProjectTransaction::TYPE_IMAGE:
      case PhabricatorProjectTransaction::TYPE_ICON:
      case PhabricatorProjectTransaction::TYPE_COLOR:
      case PhabricatorProjectTransaction::TYPE_LOCKED:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        // TODO - this is really "setPrimarySlug"
        $object->setPhrictionSlug($xaction->getNewValue());
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
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
        return;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        $object->setJoinPolicy($xaction->getNewValue());
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
        if ($old !== null) {
          $this->addSlug($object, $old);
        }
        $this->addSlug($object, $new);

        return;
      case PhabricatorProjectTransaction::TYPE_SLUGS:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add) {
          $add_slug_template = id(new PhabricatorProjectSlug())
            ->setProjectPHID($object->getPHID());
          foreach ($add as $add_slug_str) {
            $add_slug = id(clone $add_slug_template)
              ->setSlug($add_slug_str)
              ->save();
          }
        }
        if ($rem) {
          $rem_slugs = id(new PhabricatorProjectSlug())
            ->loadAllWhere('slug IN (%Ls)', $rem);
          foreach ($rem_slugs as $rem_slug) {
            $rem_slug->delete();
          }
        }

        return;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorProjectTransaction::TYPE_STATUS:
      case PhabricatorProjectTransaction::TYPE_IMAGE:
      case PhabricatorProjectTransaction::TYPE_ICON:
      case PhabricatorProjectTransaction::TYPE_COLOR:
      case PhabricatorProjectTransaction::TYPE_LOCKED:
        return;
      case PhabricatorTransactions::TYPE_EDGE:
        $edge_type = $xaction->getMetadataValue('edge:type');
        switch ($edge_type) {
          case PhabricatorProjectProjectHasMemberEdgeType::EDGECONST:
          case PhabricatorObjectHasWatcherEdgeType::EDGECONST:
            $old = $xaction->getOldValue();
            $new = $xaction->getNewValue();

            // When adding members or watchers, we add subscriptions.
            $add = array_keys(array_diff_key($new, $old));

            // When removing members, we remove their subscription too.
            // When unwatching, we leave subscriptions, since it's fine to be
            // subscribed to a project but not be a member of it.
            $edge_const = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;
            if ($edge_type == $edge_const) {
              $rem = array_keys(array_diff_key($old, $new));
            } else {
              $rem = array();
            }

            // NOTE: The subscribe is "explicit" because there's no implicit
            // unsubscribe, so Join -> Leave -> Join doesn't resubscribe you
            // if we use an implicit subscribe, even though you never willfully
            // unsubscribed. Not sure if adding implicit unsubscribe (which
            // would not write the unsubscribe row) is justified to deal with
            // this, which is a fairly weird edge case and pretty arguable both
            // ways.

            // Subscriptions caused by watches should also clearly be explicit,
            // and that case is unambiguous.

            id(new PhabricatorSubscriptionsEditor())
              ->setActor($this->requireActor())
              ->setObject($object)
              ->subscribeExplicit($add)
              ->unsubscribe($rem)
              ->save();

            if ($rem) {
              // When removing members, also remove any watches on the project.
              $edge_editor = new PhabricatorEdgeEditor();
              foreach ($rem as $rem_phid) {
                $edge_editor->removeEdge(
                  $object->getPHID(),
                  PhabricatorObjectHasWatcherEdgeType::EDGECONST,
                  $rem_phid);
              }
              $edge_editor->save();
            }
            break;
        }
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
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

        $name = last($xactions)->getNewValue();
        $name_used_already = id(new PhabricatorProjectQuery())
          ->setViewer($this->getActor())
          ->withNames(array($name))
          ->executeOne();
        if ($name_used_already &&
           ($name_used_already->getPHID() != $object->getPHID())) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Duplicate'),
            pht('Project name is already used.'),
            nonempty(last($xactions), null));
          $errors[] = $error;
        }

        $slug_builder = clone $object;
        $slug_builder->setPhrictionSlug($name);
        $slug = $slug_builder->getPrimarySlug();
        $slug_used_already = id(new PhabricatorProjectSlug())
          ->loadOneWhere('slug = %s', $slug);
        if ($slug_used_already &&
            $slug_used_already->getProjectPHID() != $object->getPHID()) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Duplicate'),
            pht('Project name can not be used due to hashtag collision.'),
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

        if ($new) {
          $slugs_used_already = id(new PhabricatorProjectSlug())
            ->loadAllWhere('slug IN (%Ls)', $new);
        } else {
          // The project doesn't have any extra slugs.
          $slugs_used_already = array();
        }

        $slugs_used_already = mgroup($slugs_used_already, 'getProjectPHID');
        foreach ($slugs_used_already as $project_phid => $used_slugs) {
          $used_slug_strs = mpull($used_slugs, 'getSlug');
          if ($project_phid == $object->getPHID()) {
            if (in_array($object->getPrimarySlug(), $used_slug_strs)) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht(
                  'Project hashtag %s is already the primary hashtag.',
                  $object->getPrimarySlug()),
                $slug_xaction);
              $errors[] = $error;
            }
            continue;
          }

          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              '%d project hashtag(s) are already used: %s.',
              count($used_slug_strs),
              implode(', ', $used_slug_strs)),
            $slug_xaction);
          $errors[] = $error;
        }

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

  /**
   * Note: this is implemented for Feed purposes.
   */
  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array();
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

  private function addSlug(
    PhabricatorLiskDAO $object,
    $name) {

    $object = (clone $object);
    $object->setPhrictionSlug($name);
    $slug = $object->getPrimarySlug();

    $slug_object = id(new PhabricatorProjectSlug())->loadOneWhere(
      'slug = %s',
      $slug);

    if ($slug_object) {
      return;
    }

    $new_slug = id(new PhabricatorProjectSlug())
      ->setSlug($slug)
      ->setProjectPHID($object->getPHID())
      ->save();
  }
}
