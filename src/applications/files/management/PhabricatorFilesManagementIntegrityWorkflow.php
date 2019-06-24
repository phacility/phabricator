<?php

final class PhabricatorFilesManagementIntegrityWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $arguments = $this->newIteratorArguments();

    $arguments[] = array(
      'name' => 'strip',
      'help' => pht(
        'DANGEROUS. Strip integrity hashes from files. This makes '.
        'files vulnerable to corruption or tampering.'),
    );

    $arguments[] = array(
      'name' => 'corrupt',
      'help' => pht(
        'Corrupt integrity hashes for given files. This is intended '.
        'for debugging.'),
    );

    $arguments[] = array(
      'name' => 'compute',
      'help' => pht(
        'Compute and update integrity hashes for files which do not '.
        'yet have them.'),
    );

    $arguments[] = array(
      'name' => 'overwrite',
      'help' => pht(
        'DANGEROUS. Recompute and update integrity hashes, overwriting '.
        'invalid hashes. This may mark corrupt or dangerous files as '.
        'valid.'),
    );

    $arguments[] = array(
      'name' => 'force',
      'short' => 'f',
      'help' => pht(
        'Execute dangerous operations without prompting for '.
        'confirmation.'),
    );


    $this
      ->setName('integrity')
      ->setSynopsis(pht('Verify or recalculate file integrity hashes.'))
      ->setArguments($arguments);
  }

  public function execute(PhutilArgumentParser $args) {
    $modes = array();

    $is_strip = $args->getArg('strip');
    if ($is_strip) {
      $modes[] = 'strip';
    }

    $is_corrupt = $args->getArg('corrupt');
    if ($is_corrupt) {
      $modes[] = 'corrupt';
    }

    $is_compute = $args->getArg('compute');
    if ($is_compute) {
      $modes[] = 'compute';
    }

    $is_overwrite = $args->getArg('overwrite');
    if ($is_overwrite) {
      $modes[] = 'overwrite';
    }

    $is_verify = !$modes;
    if ($is_verify) {
      $modes[] = 'verify';
    }

    if (count($modes) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'You have selected multiple operation modes (%s). Choose a '.
          'single mode to operate in.',
          implode(', ', $modes)));
    }

    $is_force = $args->getArg('force');
    if (!$is_force) {
      $prompt = null;
      if ($is_strip) {
        $prompt = pht(
          'Stripping integrity hashes is dangerous and makes files '.
          'vulnerable to corruption or tampering.');
      }

      if ($is_corrupt) {
        $prompt = pht(
          'Corrupting integrity hashes will prevent files from being '.
          'accessed. This mode is intended only for development and '.
          'debugging.');
      }

      if ($is_overwrite) {
        $prompt = pht(
          'Overwriting integrity hashes is dangerous and may mark files '.
          'which have been corrupted or tampered with as safe.');
      }

      if ($prompt) {
        $this->logWarn(pht('DANGEROUS'), $prompt);

        if (!phutil_console_confirm(pht('Continue anyway?'))) {
          throw new PhutilArgumentUsageException(pht('Aborted workflow.'));
        }
      }
    }

    $iterator = $this->buildIterator($args);

    $failure_count = 0;
    $total_count = 0;

    foreach ($iterator as $file) {
      $total_count++;
      $display_name = $file->getMonogram();

      $old_hash = $file->getIntegrityHash();

      if ($is_strip) {
        if ($old_hash === null) {
          $this->logInfo(
            pht('SKIPPED'),
            pht(
              'File "%s" does not have an integrity hash to strip.',
              $display_name));
        } else {
          $file
            ->setIntegrityHash(null)
            ->save();

          $this->logWarn(
            pht('STRIPPED'),
            pht(
              'Stripped integrity hash for "%s".',
              $display_name));
        }

        continue;
      }

      $need_hash = ($is_verify && $old_hash) ||
                   ($is_compute && ($old_hash === null)) ||
                   ($is_corrupt) ||
                   ($is_overwrite);
      if ($need_hash) {
        try {
          $new_hash = $file->newIntegrityHash();
        } catch (Exception $ex) {
          $failure_count++;

          $this->logFail(
            pht('ERROR'),
            pht(
              'Unable to compute integrity hash for file "%s": %s',
              $display_name,
              $ex->getMessage()));

          continue;
        }
      } else {
        $new_hash = null;
      }

      // NOTE: When running in "corrupt" mode, we only corrupt the hash if
      // we're able to compute a valid hash. Some files, like chunked files,
      // do not support integrity hashing so corrupting them would create an
      // unusual state.

      if ($is_corrupt) {
        if ($new_hash === null) {
          $this->logInfo(
            pht('IGNORED'),
            pht(
              'Storage for file "%s" does not support integrity hashing.',
              $display_name));
        } else {
          $file
            ->setIntegrityHash('<corrupted>')
            ->save();

          $this->logWarn(
            pht('CORRUPTED'),
            pht(
              'Corrupted integrity hash for file "%s".',
              $display_name));
        }

        continue;
      }

      if ($is_verify) {
        if ($old_hash === null) {
          $this->logInfo(
            pht('NONE'),
            pht(
              'File "%s" has no stored integrity hash.',
              $display_name));
        } else if ($new_hash === null) {
          $failure_count++;

          $this->logWarn(
            pht('UNEXPECTED'),
            pht(
              'Storage for file "%s" does not support integrity hashing, '.
              'but the file has an integrity hash.',
              $display_name));
        } else if (phutil_hashes_are_identical($old_hash, $new_hash)) {
          $this->logOkay(
            pht('VALID'),
            pht(
              'File "%s" has a valid integrity hash.',
              $display_name));
        } else {
          $failure_count++;

          $this->logFail(
            pht('MISMATCH'),
            pht(
              'File "%s" has an invalid integrity hash!',
              $display_name));
        }

        continue;
      }

      if ($is_compute) {
        if ($old_hash !== null) {
          $this->logInfo(
            pht('SKIP'),
            pht(
              'File "%s" already has an integrity hash.',
              $display_name));
        } else if ($new_hash === null) {
          $this->logInfo(
            pht('IGNORED'),
            pht(
              'Storage for file "%s" does not support integrity hashing.',
              $display_name));
        } else {
          $file
            ->setIntegrityHash($new_hash)
            ->save();

          $this->logOkay(
            pht('COMPUTE'),
            pht(
              'Computed and stored integrity hash for file "%s".',
              $display_name));
        }

        continue;
      }

      if ($is_overwrite) {
        $same_hash = ($old_hash !== null) &&
                     ($new_hash !== null) &&
                     phutil_hashes_are_identical($old_hash, $new_hash);

        if ($new_hash === null) {
          $this->logInfo(
            pht('IGNORED'),
            pht(
              'Storage for file "%s" does not support integrity hashing.',
              $display_name));
        } else if ($same_hash) {
          $this->logInfo(
            pht('UNCHANGED'),
            pht(
              'File "%s" already has the correct integrity hash.',
              $display_name));
        } else {
          $file
            ->setIntegrityHash($new_hash)
            ->save();

          $this->logOkay(
            pht('OVERWRITE'),
            pht(
              'Overwrote integrity hash for file "%s".',
              $display_name));
        }

        continue;
      }
    }

    if ($failure_count) {
      $this->logFail(
        pht('FAIL'),
        pht(
          'Processed %s file(s), encountered %s error(s).',
          new PhutilNumber($total_count),
          new PhutilNumber($failure_count)));
    } else {
      $this->logOkay(
        pht('DONE'),
        pht(
          'Processed %s file(s) with no errors.',
          new PhutilNumber($total_count)));
    }

    return 0;
  }

}
