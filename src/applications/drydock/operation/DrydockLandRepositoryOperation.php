<?php

final class DrydockLandRepositoryOperation
  extends DrydockRepositoryOperationType {

  const OPCONST = 'land';

  public function getOperationDescription(
    DrydockRepositoryOperation $operation,
    PhabricatorUser $viewer) {
    return pht('Land Revision');
  }

  public function getOperationCurrentStatus(
    DrydockRepositoryOperation $operation,
    PhabricatorUser $viewer) {

    $target = $operation->getRepositoryTarget();
    $repository = $operation->getRepository();
    switch ($operation->getOperationState()) {
      case DrydockRepositoryOperation::STATE_WAIT:
        return pht(
          'Waiting to land revision into %s on %s...',
          $repository->getMonogram(),
          $target);
      case DrydockRepositoryOperation::STATE_WORK:
        return pht(
          'Landing revision into %s on %s...',
          $repository->getMonogram(),
          $target);
      case DrydockRepositoryOperation::STATE_DONE:
        return pht(
          'Revision landed into %s.',
          $repository->getMonogram());
    }
  }

  public function getWorkingCopyMerges(DrydockRepositoryOperation $operation) {
    $repository = $operation->getRepository();
    $merges = array();

    $object = $operation->getObject();
    if ($object instanceof DifferentialRevision) {
      $diff = $this->loadDiff($operation);
      $merges[] = array(
        'src.uri' => $repository->getStagingURI(),
        'src.ref' => $diff->getStagingRef(),
      );
    } else {
      throw new Exception(
        pht(
          'Invalid or unknown object ("%s") for land operation, expected '.
          'Differential Revision.',
          $operation->getObjectPHID()));
    }

    return $merges;
  }

  public function applyOperation(
    DrydockRepositoryOperation $operation,
    DrydockInterface $interface) {
    $viewer = $this->getViewer();
    $repository = $operation->getRepository();

    $cmd = array();
    $arg = array();

    $object = $operation->getObject();
    if ($object instanceof DifferentialRevision) {
      $revision = $object;

      $diff = $this->loadDiff($operation);

      $dict = $diff->getDiffAuthorshipDict();
      $author_name = idx($dict, 'authorName');
      $author_email = idx($dict, 'authorEmail');

      $api_method = 'differential.getcommitmessage';
      $api_params = array(
        'revision_id' => $revision->getID(),
      );

      $commit_message = id(new ConduitCall($api_method, $api_params))
        ->setUser($viewer)
        ->execute();
    } else {
      throw new Exception(
        pht(
          'Invalid or unknown object ("%s") for land operation, expected '.
          'Differential Revision.',
          $operation->getObjectPHID()));
    }

    $target = $operation->getRepositoryTarget();
    list($type, $name) = explode(':', $target, 2);
    switch ($type) {
      case 'branch':
        $push_dst = 'refs/heads/'.$name;
        break;
      default:
        throw new Exception(
          pht(
            'Unknown repository operation target type "%s" (in target "%s").',
            $type,
            $target));
    }

    $committer_info = $this->getCommitterInfo($operation);

    // NOTE: We're doing this commit with "-F -" so we don't run into trouble
    // with enormous commit messages which might otherwise exceed the maximum
    // size of a command.

    $future = $interface->getExecFuture(
      'git -c user.name=%s -c user.email=%s commit --author %s -F - --',
      $committer_info['name'],
      $committer_info['email'],
      "{$author_name} <{$author_email}>");

    $future
      ->write($commit_message)
      ->resolvex();

    $interface->execx(
      'git push origin -- %s:%s',
      'HEAD',
      $push_dst);
  }

  private function getCommitterInfo(DrydockRepositoryOperation $operation) {
    $viewer = $this->getViewer();

    $committer_name = null;

    $author_phid = $operation->getAuthorPHID();
    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($author_phid))
      ->executeOne();

    if ($object) {
      if ($object instanceof PhabricatorUser) {
        $committer_name = $object->getUsername();
      }
    }

    if (!strlen($committer_name)) {
      $committer_name = pht('autocommitter');
    }

    // TODO: Probably let users choose a VCS email address in settings. For
    // now just make something up so we don't leak anyone's stuff.

    return array(
      'name' => $committer_name,
      'email' => 'autocommitter@example.com',
    );
  }

  private function loadDiff(DrydockRepositoryOperation $operation) {
    $viewer = $this->getViewer();
    $revision = $operation->getObject();

    $diff_phid = $operation->getProperty('differential.diffPHID');

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($diff_phid))
      ->executeOne();
    if (!$diff) {
      throw new Exception(
        pht(
          'Unable to load diff "%s".',
          $diff_phid));
    }

    $diff_revid = $diff->getRevisionID();
    $revision_id = $revision->getID();
    if ($diff_revid != $revision_id) {
      throw new Exception(
        pht(
          'Diff ("%s") has wrong revision ID ("%s", expected "%s").',
          $diff_phid,
          $diff_revid,
          $revision_id));
    }

    return $diff;
  }

  public function getBarrierToLanding(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {

    $repository = $revision->getRepository();
    if (!$repository) {
      return array(
        'title' => pht('No Repository'),
        'body' => pht(
          'This revision is not associated with a known repository. Only '.
          'revisions associated with a tracked repository can be landed '.
          'automatically.'),
      );
    }

    if (!$repository->canPerformAutomation()) {
      return array(
        'title' => pht('No Repository Automation'),
        'body' => pht(
          'The repository this revision is associated with ("%s") is not '.
          'configured to support automation. Configure automation for the '.
          'repository to enable revisions to be landed automatically.',
          $repository->getMonogram()),
      );
    }

    // TODO: At some point we should allow installs to give "land reviewed
    // code" permission to more users than "push any commit", because it is
    // a much less powerful operation. For now, just require push so this
    // doesn't do anything users can't do on their own.
    $can_push = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      DiffusionPushCapability::CAPABILITY);
    if (!$can_push) {
      return array(
        'title' => pht('Unable to Push'),
        'body' => pht(
          'You do not have permission to push to the repository this '.
          'revision is associated with ("%s"), so you can not land it.',
          $repository->getMonogram()),
      );
    }

    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
    if ($revision->getStatus() != $status_accepted) {
      return array(
        'title' => pht('Revision Not Accepted'),
        'body' => pht(
          'This revision is still under review. Only revisions which have '.
          'been accepted may land.'),
      );
    }

    // Check for other operations. Eventually this should probably be more
    // general (e.g., it's OK to land to multiple different branches
    // simultaneously) but just put this in as a sanity check for now.
    $other_operations = id(new DrydockRepositoryOperationQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($revision->getPHID()))
      ->withOperationTypes(
        array(
          $this->getOperationConstant(),
        ))
      ->withOperationStates(
        array(
          DrydockRepositoryOperation::STATE_WAIT,
          DrydockRepositoryOperation::STATE_WORK,
          DrydockRepositoryOperation::STATE_DONE,
        ))
      ->execute();

    if ($other_operations) {
      $any_done = false;
      foreach ($other_operations as $operation) {
        if ($operation->isDone()) {
          $any_done = true;
          break;
        }
      }

      if ($any_done) {
        return array(
          'title' => pht('Already Complete'),
          'body' => pht('This revision has already landed.'),
        );
      } else {
        return array(
          'title' => pht('Already In Flight'),
          'body' => pht('This revision is already landing.'),
        );
      }
    }

    return null;
  }

}
