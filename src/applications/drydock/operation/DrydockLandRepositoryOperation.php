<?php

final class DrydockLandRepositoryOperation
  extends DrydockRepositoryOperationType {

  const OPCONST = 'land';

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

    $cmd[] = 'git checkout %s';
    $arg[] = $merge_dst;

    $cmd[] = 'git merge --no-stat --squash --ff-only -- %s';
    $arg[] = $merge_src;

    $cmd[] = 'git -c user.name=%s -c user.email=%s commit --author %s -m %s';
    $arg[] = 'autocommitter';
    $arg[] = 'autocommitter@example.com';
    $arg[] = 'autoauthor <autoauthor@example.com>';
    $arg[] = pht('(Automerge!)');

    $cmd[] = 'git push origin -- %s:%s';
    $arg[] = 'HEAD';
    $arg[] = $push_dst;

    $cmd = implode(' && ', $cmd);
    $argv = array_merge(array($cmd), $arg);

    $result = call_user_func_array(
      array($interface, 'execx'),
      $argv);
  }

}
