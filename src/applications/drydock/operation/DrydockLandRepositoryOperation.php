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

      $cmd[] = 'git fetch --no-tags -- %s +%s:%s';
      $arg[] = $repository->getStagingURI();
      $arg[] = $diff->getStagingRef();
      $arg[] = $diff->getStagingRef();

      $merge_src = $diff->getStagingRef();

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
        $merge_dst = 'refs/remotes/origin/'.$name;
        break;
      default:
        throw new Exception(
          pht(
            'Unknown repository operation target type "%s" (in target "%s").',
            $type,
            $target));
    }

    $committer_info = $this->getCommitterInfo($operation);

    $cmd[] = 'git checkout %s';
    $arg[] = $merge_dst;

    $cmd[] = 'git merge --no-stat --squash --ff-only -- %s';
    $arg[] = $merge_src;

    $cmd[] = 'git -c user.name=%s -c user.email=%s commit --author %s -m %s';

    $arg[] = $committer_info['name'];
    $arg[] = $committer_info['email'];

    $arg[] = "{$author_name} <{$author_email}>";
    $arg[] = $commit_message;

    $cmd[] = 'git push origin -- %s:%s';
    $arg[] = 'HEAD';
    $arg[] = $push_dst;

    $cmd = implode(' && ', $cmd);
    $argv = array_merge(array($cmd), $arg);

    $result = call_user_func_array(
      array($interface, 'execx'),
      $argv);
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

}
