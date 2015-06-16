<?php

final class PhabricatorDestructionEngine extends Phobject {

  private $rootLogID;

  public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  public function destroyObject(PhabricatorDestructibleInterface $object) {
    $log = id(new PhabricatorSystemDestructionLog())
      ->setEpoch(time())
      ->setObjectClass(get_class($object));

    if ($this->rootLogID) {
      $log->setRootLogID($this->rootLogID);
    }

    $object_phid = null;
    if (method_exists($object, 'getPHID')) {
      try {
        $object_phid = $object->getPHID();
        $log->setObjectPHID($object_phid);
      } catch (Exception $ex) {
        // Ignore.
      }
    }

    if (method_exists($object, 'getMonogram')) {
      try {
        $log->setObjectMonogram($object->getMonogram());
      } catch (Exception $ex) {
        // Ignore.
      }
    }

    $log->save();

    if (!$this->rootLogID) {
      $this->rootLogID = $log->getID();
    }

    $object->destroyObjectPermanently($this);

    if ($object_phid) {
      $this->destroyEdges($object_phid);

      if ($object instanceof PhabricatorApplicationTransactionInterface) {
        $template = $object->getApplicationTransactionTemplate();
        $this->destroyTransactions($template, $object_phid);
      }

      $this->destroyWorkerTasks($object_phid);
      $this->destroyNotifications($object_phid);
    }

    // Nuke any Herald transcripts of the object, because they may contain
    // field data.

    // TODO: Define an interface so we don't have to do this for transactions
    // and other objects with no Herald adapters?
    $transcripts = id(new HeraldTranscript())->loadAllWhere(
      'objectPHID = %s',
      $object_phid);
    foreach ($transcripts as $transcript) {
      $transcript->destroyObjectPermanently($this);
    }

    // TODO: Remove stuff from search indexes?

    if ($object instanceof PhabricatorFlaggableInterface) {
      $flags = id(new PhabricatorFlag())->loadAllWhere(
        'objectPHID = %s', $object_phid);

      foreach ($flags as $flag) {
        $flag->delete();
      }
    }

    $flags = id(new PhabricatorFlag())->loadAllWhere(
      'ownerPHID = %s', $object_phid);
    foreach ($flags as $flag) {
        $flag->delete();
      }

    if ($object instanceof PhabricatorTokenReceiverInterface) {
      $tokens = id(new PhabricatorTokenGiven())->loadAllWhere(
        'objectPHID = %s', $object_phid);

      foreach ($tokens as $token) {
        $token->delete();
      }
    }
  }

  private function destroyEdges($src_phid) {
    try {
      $edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(array($src_phid))
        ->execute();
    } catch (Exception $ex) {
      // This is (presumably) a "no edges for this PHID type" exception.
      return;
    }

    $editor = new PhabricatorEdgeEditor();
    foreach ($edges as $type => $type_edges) {
      foreach ($type_edges as $src => $src_edges) {
        foreach ($src_edges as $dst => $edge) {
          $editor->removeEdge($edge['src'], $edge['type'], $edge['dst']);
        }
      }
    }
    $editor->save();
  }

  private function destroyTransactions(
    PhabricatorApplicationTransaction $template,
    $object_phid) {

    $xactions = $template->loadAllWhere('objectPHID = %s', $object_phid);
    foreach ($xactions as $xaction) {
      $this->destroyObject($xaction);
    }
  }

  private function destroyWorkerTasks($object_phid) {
    $tasks = id(new PhabricatorWorkerActiveTask())->loadAllWhere(
      'objectPHID = %s',
      $object_phid);

    foreach ($tasks as $task) {
      $task->archiveTask(
        PhabricatorWorkerArchiveTask::RESULT_CANCELLED,
        0);
    }
  }

  private function destroyNotifications($object_phid) {
    $table = new PhabricatorFeedStoryNotification();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE primaryObjectPHID = %s',
      $table->getTableName(),
      $object_phid);
  }

}
