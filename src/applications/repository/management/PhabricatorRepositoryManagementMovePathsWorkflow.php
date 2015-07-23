<?php

final class PhabricatorRepositoryManagementMovePathsWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('move-paths')
      ->setSynopsis(pht('Move repository local paths.'))
      ->setArguments(
        array(
          array(
            'name' => 'from',
            'param' => 'prefix',
            'help' => pht('Move paths with this prefix.'),
          ),
          array(
            'name' => 'to',
            'param' => 'prefix',
            'help' => pht('Replace matching prefixes with this string.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->execute();
    if (!$repos) {
      $console->writeErr("%s\n", pht('There are no repositories.'));
      return 0;
    }

    $from = $args->getArg('from');
    if (!strlen($from)) {
      throw new Exception(
        pht(
          'You must specify a path prefix to move from with --from.'));
    }

    $to = $args->getArg('to');
    if (!strlen($to)) {
      throw new Exception(
        pht(
          'You must specify a path prefix to move to with --to.'));
    }

    $rows = array();

    $any_changes = false;
    foreach ($repos as $repo) {
      $src = $repo->getLocalPath();

      $row = array(
        'repository' => $repo,
        'move' => false,
        'monogram' => $repo->getMonogram(),
        'src' => $src,
        'dst' => '',
      );

      if (strncmp($src, $from, strlen($from))) {
        $row['action'] = pht('Ignore');
      } else {
        $dst = $to.substr($src, strlen($from));

        $row['action'] = phutil_console_format('**%s**', pht('Move'));
        $row['dst'] = $dst;
        $row['move'] = true;
        $any_changes = true;
      }

      $rows[] = $row;
    }

    $table = id(new PhutilConsoleTable())
      ->addColumn(
        'action',
        array(
          'title' => pht('Action'),
        ))
      ->addColumn(
        'monogram',
        array(
          'title' => pht('Repository'),
        ))
      ->addColumn(
        'src',
        array(
          'title' => pht('Src'),
        ))
      ->addColumn(
        'dst',
        array(
          'title' => pht('dst'),
        ))
      ->setBorders(true);

    foreach ($rows as $row) {
      $display = array_select_keys(
        $row,
        array(
          'action',
          'monogram',
          'src',
          'dst',
        ));
      $table->addRow($display);
    }

    $table->draw();

    if (!$any_changes) {
      $console->writeOut(pht('No matching repositories.')."\n");
      return 0;
    }

    $prompt = pht('Apply these changes?');
    if (!phutil_console_confirm($prompt)) {
      throw new Exception(pht('Declining to apply changes.'));
    }

    foreach ($rows as $row) {
      if (empty($row['move'])) {
        continue;
      }

      $repo = $row['repository'];
      $details = $repo->getDetails();
      $details['local-path'] = $row['dst'];

      queryfx(
        $repo->establishConnection('w'),
        'UPDATE %T SET details = %s WHERE id = %d',
        $repo->getTableName(),
        phutil_json_encode($details),
        $repo->getID());
    }

    $console->writeOut(pht('Applied changes.')."\n");
    return 0;
  }

}
