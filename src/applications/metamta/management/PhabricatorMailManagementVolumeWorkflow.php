<?php

final class PhabricatorMailManagementVolumeWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('volume')
      ->setSynopsis(
        pht('Show how much mail users have received in the last 30 days.'))
      ->setExamples(
        '**volume**')
      ->setArguments(
        array(
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $since = (PhabricatorTime::getNow() - phutil_units('30 days in seconds'));
    $until = PhabricatorTime::getNow();

    $mails = id(new PhabricatorMetaMTAMailQuery())
      ->setViewer($viewer)
      ->withDateCreatedBetween($since, $until)
      ->execute();

    $unfiltered = array();

    foreach ($mails as $mail) {
      $unfiltered_actors = mpull($mail->loadAllActors(), 'getPHID');
      foreach ($unfiltered_actors as $phid) {
        if (empty($unfiltered[$phid])) {
          $unfiltered[$phid] = 0;
        }
        $unfiltered[$phid]++;
      }
    }

    arsort($unfiltered);

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
        ));

    $handles = $viewer->loadHandles(array_keys($unfiltered));
    $names = mpull(iterator_to_array($handles), 'getName', 'getPHID');

    foreach ($unfiltered as $phid => $count) {
      $table->addRow(
        array(
          'user' => idx($names, $phid),
          'unfiltered' => $count,
        ));
    }

    $table->draw();

    echo "\n";
    echo pht('Mail sent in the last 30 days.')."\n";
    echo pht(
      '"Unfiltered" is raw volume before preferences were applied.')."\n";
    echo "\n";

    return 0;
  }

}
