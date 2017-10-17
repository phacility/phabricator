<?php

final class PhabricatorSearchManagementNgramsWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('ngrams')
      ->setSynopsis(
        pht(
          'Recompute common ngrams. This is an advanced workflow that '.
          'can harm search quality if used improperly.'))
      ->setArguments(
        array(
          array(
            'name' => 'reset',
            'help' => pht('Reset all common ngram records.'),
          ),
          array(
            'name' => 'threshold',
            'param' => 'threshold',
            'help' => pht(
              'Prune ngrams present in more than this fraction of '.
              'documents. Provide a value between 0.0 and 1.0.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $min_documents = 4096;

    $is_reset = $args->getArg('reset');
    $threshold = $args->getArg('threshold');

    if ($is_reset && $threshold !== null) {
      throw new PhutilArgumentUsageException(
        pht('Specify either --reset or --threshold, not both.'));
    }

    if (!$is_reset && $threshold === null) {
      throw new PhutilArgumentUsageException(
        pht('Specify either --reset or --threshold.'));
    }

    if (!$is_reset) {
      if (!is_numeric($threshold)) {
        throw new PhutilArgumentUsageException(
          pht('Specify a numeric threshold between 0 and 1.'));
      }

      $threshold = (double)$threshold;
      if ($threshold <= 0 || $threshold >= 1) {
        throw new PhutilArgumentUsageException(
          pht('Threshold must be greater than 0.0 and less than 1.0.'));
      }
    }

    $all_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorFerretInterface')
      ->execute();

    foreach ($all_objects as $object) {
      $engine = $object->newFerretEngine();
      $conn = $object->establishConnection('w');
      $display_name = get_class($object);

      if ($is_reset) {
        echo tsprintf(
          "%s\n",
          pht(
            'Resetting common ngrams for "%s".',
            $display_name));

        queryfx(
          $conn,
          'DELETE FROM %T',
          $engine->getCommonNgramsTableName());
        continue;
      }

      $document_count = queryfx_one(
        $conn,
        'SELECT COUNT(*) N FROM %T',
        $engine->getDocumentTableName());
      $document_count = $document_count['N'];

      if ($document_count < $min_documents) {
        echo tsprintf(
          "%s\n",
          pht(
            'Too few documents of type "%s" for any ngrams to be common.',
            $display_name));
        continue;
      }

      $min_frequency = (int)ceil($document_count * $threshold);
      $common_ngrams = queryfx_all(
        $conn,
        'SELECT ngram, COUNT(*) N FROM %T
          GROUP BY ngram
          HAVING N >= %d',
        $engine->getNgramsTableName(),
        $min_frequency);

      if (!$common_ngrams) {
        echo tsprintf(
          "%s\n",
          pht(
            'No new common ngrams exist for "%s".',
            $display_name));
        continue;
      }

      $sql = array();
      foreach ($common_ngrams as $ngram) {
        $sql[] = qsprintf(
          $conn,
          '(%s, 1)',
          $ngram['ngram']);
      }

      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn,
          'INSERT IGNORE INTO %T (ngram, needsCollection)
            VALUES %Q',
          $engine->getCommonNgramsTableName(),
          $chunk);
      }

      echo tsprintf(
        "%s\n",
        pht(
          'Updated common ngrams for "%s".',
          $display_name));
    }
  }

}
