<?php

final class PhabricatorApplicationEmailCommandsController
  extends PhabricatorApplicationsController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $application = $request->getURIData('application');

    $selected = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->executeOne();
    if (!$selected) {
      return new Aphront404Response();
    }

    $specs = $selected->getMailCommandObjects();
    $type = $request->getURIData('type');
    if (empty($specs[$type])) {
      return new Aphront404Response();
    }

    $spec = $specs[$type];
    $commands = MetaMTAEmailTransactionCommand::getAllCommandsForObject(
      $spec['object']);

    $commands = msort($commands, 'getCommand');

    $content = array();

    $content[] = '= '.pht('Quick Reference');
    $table = array();
    $table[] = '| '.pht('Command').' | '.pht('Summary').' |';
    $table[] = '|---|---|';
    foreach ($commands as $command) {
      $summary = $command->getCommandSummary();
      $table[] = '| '.$command->getCommandSyntax().' | '.$summary;
    }
    $table = implode("\n", $table);
    $content[] = $table;

    foreach ($commands as $command) {
      $content[] = '== !'.$command->getCommand().' ==';
      $content[] = $command->getCommandSummary();

      $aliases = $command->getCommandAliases();
      if ($aliases) {
        foreach ($aliases as $key => $alias) {
          $aliases[$key] = '!'.$alias;
        }
        $aliases = implode(', ', $aliases);
      } else {
        $aliases = '//None//';
      }

      $syntax = $command->getCommandSyntax();

      $table = array();
      $table[] = '| '.pht('Property').' | '.pht('Value');
      $table[] = '|---|---|';
      $table[] = '| **'.pht('Syntax').'** | '.$syntax;
      $table[] = '| **'.pht('Aliases').'** | '.$aliases;
      $table[] = '| **'.pht('Class').'** | `'.get_class($command).'`';
      $table = implode("\n", $table);

      $content[] = $table;

      $description = $command->getCommandDescription();
      if ($description) {
        $content[] = $description;
      }
    }

    $content = implode("\n\n", $content);

    $title = $spec['name'];

    $crumbs = $this->buildApplicationCrumbs();
    $this->addApplicationCrumb($crumbs, $selected);
    $crumbs->addTextCrumb($title);

    $content_box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild(
        PhabricatorMarkupEngine::renderOneObject(
          id(new PhabricatorMarkupOneOff())->setContent($content),
          'default',
          $viewer));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($content_box);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));

  }


}
