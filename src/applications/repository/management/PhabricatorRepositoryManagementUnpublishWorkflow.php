<?php

final class PhabricatorRepositoryManagementUnpublishWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('unpublish')
      ->setExamples(
        '**unpublish** [__options__] __repository__')
      ->setSynopsis(
        pht(
          'Unpublish all feed stories and notifications that a repository '.
          'has generated. Keep expectations low; can not rewind time.'))
      ->setArguments(
        array(
          array(
            'name' => 'force',
            'help' => pht('Do not prompt for confirmation.'),
          ),
          array(
            'name' => 'dry-run',
            'help' => pht('Do not perform any writes.'),
          ),
          array(
            'name' => 'repositories',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();
    $is_force = $args->getArg('force');
    $is_dry_run = $args->getArg('dry-run');

    $repositories = $this->loadLocalRepositories($args, 'repositories');
    if (count($repositories) !== 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one repository to unpublish.'));
    }
    $repository = head($repositories);

    if (!$is_force) {
      echo tsprintf(
        "%s\n",
        pht(
          'This script will unpublish all feed stories and notifications '.
          'which a repository generated during import. This action can not '.
          'be undone.'));

      $prompt = pht(
        'Permanently unpublish "%s"?',
        $repository->getDisplayName());
      if (!phutil_console_confirm($prompt)) {
        throw new PhutilArgumentUsageException(
          pht('User aborted workflow.'));
      }
    }

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->execute();

    echo pht("Will unpublish %s commits.\n", count($commits));

    foreach ($commits as $commit) {
      $this->unpublishCommit($commit, $is_dry_run);
    }

    return 0;
  }

  private function unpublishCommit(
    PhabricatorRepositoryCommit $commit,
    $is_dry_run) {
    $viewer = $this->getViewer();

    echo tsprintf(
      "%s\n",
      pht(
        'Unpublishing commit "%s".',
        $commit->getMonogram()));

    $stories = id(new PhabricatorFeedQuery())
      ->setViewer($viewer)
      ->withFilterPHIDs(array($commit->getPHID()))
      ->execute();

    if ($stories) {
      echo tsprintf(
        "%s\n",
        pht(
          'Found %s feed storie(s).',
          count($stories)));

      if (!$is_dry_run) {
        $engine = new PhabricatorDestructionEngine();
        foreach ($stories as $story) {
          $story_data = $story->getStoryData();
          $engine->destroyObject($story_data);
        }

        echo tsprintf(
          "%s\n",
          pht(
            'Destroyed %s feed storie(s).',
            count($stories)));
      }
    }

    $edge_types = array(
      PhabricatorObjectMentionsObjectEdgeType::EDGECONST => true,
      DiffusionCommitHasTaskEdgeType::EDGECONST => true,
      DiffusionCommitHasRevisionEdgeType::EDGECONST => true,
      DiffusionCommitRevertsCommitEdgeType::EDGECONST => true,
    );

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($commit->getPHID()))
      ->withEdgeTypes(array_keys($edge_types));
    $edges = $query->execute();

    foreach ($edges[$commit->getPHID()] as $type => $edge_list) {
      foreach ($edge_list as $edge) {
        $dst = $edge['dst'];

        echo tsprintf(
          "%s\n",
          pht(
            'Commit "%s" has edge of type "%s" to object "%s".',
            $commit->getMonogram(),
            $type,
            $dst));

        $object = id(new PhabricatorObjectQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($dst))
          ->executeOne();
        if ($object) {
          if ($object instanceof PhabricatorApplicationTransactionInterface) {
            $this->unpublishEdgeTransaction(
              $commit,
              $type,
              $object,
              $is_dry_run);
          }
        }
      }
    }
  }

  private function unpublishEdgeTransaction(
    $src,
    $type,
    PhabricatorApplicationTransactionInterface $dst,
    $is_dry_run) {
    $viewer = $this->getViewer();

    $query = PhabricatorApplicationTransactionQuery::newQueryForObject($dst)
      ->setViewer($viewer)
      ->withObjectPHIDs(array($dst->getPHID()));

    $xactions = id(clone $query)
      ->withTransactionTypes(
        array(
          PhabricatorTransactions::TYPE_EDGE,
        ))
      ->execute();

    $type_obj = PhabricatorEdgeType::getByConstant($type);
    $inverse_type = $type_obj->getInverseEdgeConstant();

    $engine = new PhabricatorDestructionEngine();
    foreach ($xactions as $xaction) {
      $edge_type = $xaction->getMetadataValue('edge:type');
      if ($edge_type != $inverse_type) {
        // Some other type of edge was edited.
        continue;
      }

      $record = PhabricatorEdgeChangeRecord::newFromTransaction($xaction);
      $changed = $record->getChangedPHIDs();
      if ($changed !== array($src->getPHID())) {
        // Affected objects were not just the object we're unpublishing.
        continue;
      }

      echo tsprintf(
        "%s\n",
        pht(
          'Found edge transaction "%s" on object "%s" for type "%s".',
          $xaction->getPHID(),
          $dst->getPHID(),
          $type));

      if (!$is_dry_run) {
        $engine->destroyObject($xaction);

        echo tsprintf(
          "%s\n",
          pht(
            'Destroyed transaction "%s" on object "%s".',
            $xaction->getPHID(),
            $dst->getPHID()));
      }
    }

    if ($type === DiffusionCommitHasTaskEdgeType::EDGECONST) {
      $xactions = id(clone $query)
        ->withTransactionTypes(
          array(
            ManiphestTaskStatusTransaction::TRANSACTIONTYPE,
          ))
        ->execute();

      if ($xactions) {
        foreach ($xactions as $xaction) {
          $metadata = $xaction->getMetadata();
          if (idx($metadata, 'commitPHID') === $src->getPHID()) {
            echo tsprintf(
              "%s\n",
              pht(
                'MANUAL Task "%s" was likely closed improperly by "%s".',
                $dst->getMonogram(),
                $src->getMonogram()));
          }
        }
      }
    }

    if ($type === DiffusionCommitHasRevisionEdgeType::EDGECONST) {
      $xactions = id(clone $query)
        ->withTransactionTypes(
          array(
            DifferentialRevisionCloseTransaction::TRANSACTIONTYPE,
          ))
        ->execute();

      if ($xactions) {
        foreach ($xactions as $xaction) {
          $metadata = $xaction->getMetadata();
          if (idx($metadata, 'commitPHID') === $src->getPHID()) {
            echo tsprintf(
              "%s\n",
              pht(
                'MANUAL Revision "%s" was likely closed improperly by "%s".',
                $dst->getMonogram(),
                $src->getMonogram()));
          }
        }
      }
    }

    if (!$is_dry_run) {
      id(new PhabricatorEdgeEditor())
        ->removeEdge($src->getPHID(), $type, $dst->getPHID())
        ->save();
      echo tsprintf(
        "%s\n",
        pht(
          'Destroyed edge of type "%s" between "%s" and "%s".',
          $type,
          $src->getPHID(),
          $dst->getPHID()));
    }
  }


}
