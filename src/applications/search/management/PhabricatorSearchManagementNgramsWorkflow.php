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
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $is_reset = $args->getArg('reset');

    $all_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorFerretInterface')
      ->execute();

    $min_documents = 4096;
    $threshold = 0.15;

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
