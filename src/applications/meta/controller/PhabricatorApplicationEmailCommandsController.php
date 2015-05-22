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

    $content[] = '= '.pht('Mail Commands Overview');
    $content[] = pht(
      'After configuring Phabricator to process inbound mail, you can '.
      'interact with objects (like tasks and revisions) over email. For '.
      'information on configuring Phabricator, see '.
      '**[[ %s | Configuring Inbound Email ]]**.'.
      "\n\n".
      'In most cases, you can reply to email you receive from Phabricator '.
      'to leave comments. You can also use **mail commands** to take a '.
      'greater range of actions (like claiming a task or requesting changes '.
      'to a revision) without needing to log in to the web UI.'.
      "\n\n".
      'Mail commands are keywords which start with an exclamation point, '.
      'like `!claim`. Some commands may take parameters, like '.
      "`!assign alincoln`.\n\n".
      'To use mail commands, write one command per line at the beginning '.
      'or end of your mail message. For example, you could write this in a '.
      'reply to task email to claim the task:'.
      "\n\n```\n!claim\n\nI'll take care of this.\n```\n\n\n".
      "When Phabricator receives your mail, it will process any commands ".
      "first, then post the remaining message body as a comment. You can ".
      "execute multiple commands at once:".
      "\n\n```\n!assign alincoln\n!close\n\nI just talked to @alincoln, ".
      "and he showed me that he fixed this.\n```\n",
      PhabricatorEnv::getDoclink('Configuring Inbound Email'));

    $content[] = '= '.$spec['header'];
    $content[] = $spec['summary'];

    $content[] = '= '.pht('Quick Reference');
    $content[] = pht(
      'This table summarizes the available mail commands. For details on a '.
      'specific command, see the command section below.');
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

    $content_box = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($content),
      'default',
      $viewer);

    $info_view = null;
    if (!PhabricatorEnv::getEnvConfig('metamta.reply-handler-domain')) {
      $error = pht(
        "Phabricator is not currently configured to accept inbound mail. ".
        "You won't be able to interact with objects over email until ".
        "inbound mail is set up.");
      $info_view = id(new PHUIInfoView())
        ->setErrors(array($error));
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $document = id(new PHUIDocumentView())
      ->setHeader($header)
      ->setFontKit(PHUIDocumentView::FONT_SOURCE_SANS)
      ->appendChild($info_view)
      ->appendChild($content_box);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $document,
      ),
      array(
        'title' => $title,
      ));

  }


}
