<?php

final class HarbormasterManagementArchiveLogsWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('archive-logs')
      ->setExamples('**archive-logs** [__options__] --mode __mode__')
      ->setSynopsis(pht('Compress, decompress, store or destroy build logs.'))
      ->setArguments(
        array(
          array(
            'name' => 'mode',
            'param' => 'mode',
            'help' => pht(
              'Use "plain" to remove encoding, or "compress" to compress '.
              'logs.'),
          ),
          array(
            'name' => 'details',
            'help' => pht(
              'Show more details about operations as they are performed. '.
              'Slow! But also very reassuring!'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $mode = $args->getArg('mode');
    if (!$mode) {
      throw new PhutilArgumentUsageException(
        pht('Choose an archival mode with --mode.'));
    }

    $valid_modes = array(
      'plain',
      'compress',
    );

    $valid_modes = array_fuse($valid_modes);
    if (empty($valid_modes[$mode])) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unknown mode "%s". Valid modes are: %s.',
          $mode,
          implode(', ', $valid_modes)));
    }

    $log_table = new HarbormasterBuildLog();
    $logs = new LiskMigrationIterator($log_table);

    $show_details = $args->getArg('details');

    if ($show_details) {
      $total_old = 0;
      $total_new = 0;
    }

    foreach ($logs as $log) {
      echo tsprintf(
        "%s\n",
        pht('Processing Harbormaster build log #%d...', $log->getID()));

      if ($show_details) {
        $old_stats = $this->computeDetails($log);
      }

      switch ($mode) {
        case 'plain':
          $log->decompressLog();
          break;
        case 'compress':
          $log->compressLog();
          break;
      }

      if ($show_details) {
        $new_stats = $this->computeDetails($log);
        $this->printStats($old_stats, $new_stats);

        $total_old += $old_stats['bytes'];
        $total_new += $new_stats['bytes'];
      }
    }

    if ($show_details) {
      echo tsprintf(
        "%s\n",
        pht(
          'Done. Total byte size of affected logs: %s -> %s.',
          new PhutilNumber($total_old),
          new PhutilNumber($total_new)));
    }

    return 0;
  }

  private function computeDetails(HarbormasterBuildLog $log) {
    $bytes = 0;
    $chunks = 0;
    $hash = hash_init('sha1');

    foreach ($log->newChunkIterator() as $chunk) {
      $bytes += strlen($chunk->getChunk());
      $chunks++;
      hash_update($hash, $chunk->getChunkDisplayText());
    }

    return array(
      'bytes' => $bytes,
      'chunks' => $chunks,
      'hash' => hash_final($hash),
    );
  }

  private function printStats(array $old_stats, array $new_stats) {
    echo tsprintf(
      "    %s\n",
      pht(
        '%s: %s -> %s',
        pht('Stored Bytes'),
        new PhutilNumber($old_stats['bytes']),
        new PhutilNumber($new_stats['bytes'])));

    echo tsprintf(
      "    %s\n",
      pht(
        '%s: %s -> %s',
        pht('Stored Chunks'),
        new PhutilNumber($old_stats['chunks']),
        new PhutilNumber($new_stats['chunks'])));

    echo tsprintf(
      "    %s\n",
      pht(
        '%s: %s -> %s',
        pht('Data Hash'),
        $old_stats['hash'],
        $new_stats['hash']));

    if ($old_stats['hash'] !== $new_stats['hash']) {
      throw new Exception(
        pht('Log data hashes differ! Something is tragically wrong!'));
    }
  }

}
