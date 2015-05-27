<?php

final class PhabricatorTypeaheadFunctionHelpController
  extends PhabricatorTypeaheadDatasourceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $class = $request->getURIData('class');

    $sources = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorTypeaheadDatasource')
      ->loadObjects();
    if (!isset($sources[$class])) {
      return new Aphront404Response();
    }

    $source = $sources[$class];

    $application_class = $source->getDatasourceApplicationClass();
    if ($application_class) {
      $result = id(new PhabricatorApplicationQuery())
        ->setViewer($this->getViewer())
        ->withClasses(array($application_class))
        ->execute();
      if (!$result) {
        return new Aphront404Response();
      }
    }

    $source->setViewer($viewer);

    $title = pht('Typeahead Function Help');

    $functions = $source->getAllDatasourceFunctions();
    ksort($functions);

    $content = array();

    $content[] = '= '.pht('Overview');
    $content[] = pht(
      'Typeahead functions are an advanced feature which allow you to build '.
      'more powerful queries. This document explains functions available '.
      'for the selected control.'.
      "\n\n".
      'For general help with search, see the [[ %s | Search User Guide ]] in '.
      'the documentation.'.
      "\n\n".
      'Note that different controls support //different// functions '.
      '(depending on what the control is doing), so these specific functions '.
      'may not work everywhere. You can always check the help for a control '.
      'to review which functions are available for that control.',
      PhabricatorEnv::getDoclink('Search User Guide'));

    $table = array();

    $table_header = array(
      pht('Function'),
      pht('Token Name'),
      pht('Summary'),
    );
    $table[] = '| '.implode(' | ', $table_header).' |';
    $table[] = '|---|---|---|';

    foreach ($functions as $function => $spec) {
      $spec = $spec + array(
        'summary' => null,
        'arguments' => null,
      );

      if (idx($spec, 'arguments')) {
        $signature = '**'.$function.'(**//'.$spec['arguments'].'//**)**';
      } else {
        $signature = '**'.$function.'()**';
      }

      $name = idx($spec, 'name', '');
      $summary = idx($spec, 'summary', '');

      $table[] = '| '.$signature.' | '.$name.' | '.$summary.' |';
    }

    $table = implode("\n", $table);
    $content[] = '= '.pht('Function Quick Reference');
    $content[] = pht(
      'This table briefly describes available functions for this control. '.
      'For details on a particular function, see the corresponding section '.
      'below.');
    $content[] = $table;

    $content[] = '= '.pht('Using Typeahead Functions');
    $content[] = pht(
      "In addition to typing user and project names to build queries, you can ".
      "also type the names of special functions which give you more options ".
      "and the ability to express more complex queries.\n\n".
      "Functions have an internal name (like `%s`) and a human-readable name, ".
      "like `Current Viewer`. In general, you can type either one to select ".
      "the function. You can also click the {nav icon=search} button on any ".
      "typeahead control to browse available functions and find this ".
      "documentation.\n\n".
      "This documentation uses the internal names to make it clear where ".
      "tokens begin and end. Specifically, you will find queries written ".
      "out like this in the documentation:\n\n%s\n\n".
      "When this query is actually shown in the control, it will look more ".
      "like this:\n\n%s",
      'viewer()',
      '> viewer(), alincoln',
      '> {nav Current Viewer} {nav alincoln (Abraham Lincoln)}');


    $middot = "\xC2\xB7";
    foreach ($functions as $function => $spec) {
      $arguments = idx($spec, 'arguments', '');
      $name = idx($spec, 'name');
      $content[] = '= '.$function.'('.$arguments.') '.$middot.' '.$name;
      $content[] = $spec['description'];
    }

    $content = implode("\n\n", $content);

    $content_box = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($content),
      'default',
      $viewer);

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $document = id(new PHUIDocumentView())
      ->setHeader($header)
      ->setFontKit(PHUIDocumentView::FONT_SOURCE_SANS)
      ->appendChild($content_box);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Function Help'));

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
