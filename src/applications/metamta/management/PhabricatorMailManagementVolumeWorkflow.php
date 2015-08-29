<?php

final class PhabricatorMailManagementVolumeWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('volume')
      ->setSynopsis(
        pht('Show how much mail users have received recently.'))
      ->setExamples(
        '**volume**')
      ->setArguments(
        array(
          array(
            'name'    => 'days',
            'param'   => 'days',
            'default' => 30,
            'help'    => pht(
              'Number of days back (default 30).'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $days = (int)$args->getArg('days');
    if ($days < 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Period specified with --days must be at least 1.'));
    }

    $duration = phutil_units("{$days} days in seconds");

    $since = (PhabricatorTime::getNow() - $duration);
    $until = PhabricatorTime::getNow();

    $mails = id(new PhabricatorMetaMTAMailQuery())
      ->setViewer($viewer)
      ->withDateCreatedBetween($since, $until)
      ->execute();

    $unfiltered = array();
    $delivered = array();

    foreach ($mails as $mail) {
      // Count messages we attempted to deliver. This includes messages which
      // were voided by preferences or other rules.
      $unfiltered_actors = mpull($mail->loadAllActors(), 'getPHID');
      foreach ($unfiltered_actors as $phid) {
        if (empty($unfiltered[$phid])) {
          $unfiltered[$phid] = 0;
        }
        $unfiltered[$phid]++;
      }

      // Now, count mail we actually delivered.
      $result = $mail->getDeliveredActors();
      if ($result) {
        foreach ($result as $actor_phid => $actor_info) {
          if (!$actor_info['deliverable']) {
            continue;
          }
          if (empty($delivered[$actor_phid])) {
            $delivered[$actor_phid] = 0;
          }
          $delivered[$actor_phid]++;
        }
      }
    }

    // Sort users by delivered mail, then unfiltered mail.
    arsort($delivered);
    arsort($unfiltered);
    $delivered = $delivered + array_fill_keys(array_keys($unfiltered), 0);

    $table = id(new PhutilConsoleTable())
      ->setBorders(true)
      ->addColumn(
        'user',
        array(
          'title' => pht('User'),
        ))
      ->addColumn(
        'unfiltered',
        array(
          'title' => pht('Unfiltered'),
        ))
      ->addColumn(
        'delivered',
        array(
          'title' => pht('Delivered'),
        ));

    $handles = $viewer->loadHandles(array_keys($unfiltered));
    $names = mpull(iterator_to_array($handles), 'getName', 'getPHID');

    foreach ($delivered as $phid => $delivered_count) {
      $unfiltered_count = idx($unfiltered, $phid, 0);
      $table->addRow(
        array(
          'user' => idx($names, $phid),
          'unfiltered' => $unfiltered_count,
          'delivered' => $delivered_count,
        ));
    }

    $table->draw();

    echo "\n";
    echo pht(
      'Mail sent in the last %s day(s).',
      new PhutilNumber($days))."\n";
    echo pht(
      '"Unfiltered" is raw volume before rules applied.')."\n";
    echo pht(
      '"Delivered" shows email actually sent.')."\n";
    echo "\n";

    return 0;
  }

}
