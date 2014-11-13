<?php

final class PhabricatorProjectWikiExplainController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    return $this->newDialog()
      ->setTitle(pht('Wikis Have Changed'))
      ->appendParagraph(
        pht(
          'Wiki pages in Phriction have been upgraded to have more powerful '.
          'support for policies and access control. Each page can now have '.
          'its own policies.'))
      ->appendParagraph(
        pht(
          'This change obsoletes dedicated project wiki pages and '.
          'resolves a number of issues they had: you can now have '.
          'multiple wiki pages for a project, put them anywhere, give '.
          'them custom access controls, and rename them (or the project) '.
          'more easily and with fewer issues.'))
      ->appendParagraph(
        pht(
          'If you want to point users of this project to specific wiki '.
          'pages with relevant documentation or information, edit the project '.
          'description and add links. You can use the %s syntax to link to a '.
          'wiki page.',
          phutil_tag('tt', array(), '[[ example/page/ ]]')))
      ->addCancelButton('/', pht('Okay'));
  }

}
