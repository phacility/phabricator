<?php

final class PhabricatorRepositoryArcanistProjectEditController
  extends PhabricatorRepositoryController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorRepositoryArcanistProject())->load($this->id);
    if (!$project) {
      return new Aphront404Response();
    }

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($user)
      ->execute();
    $repos = array(
      0 => 'None',
    );
    foreach ($repositories as $repository) {
      $callsign = $repository->getCallsign();
      $name = $repository->getname();
      $repos[$repository->getID()] = "r{$callsign} ({$name})";
    }
    // note "None" will still be first thanks to 'r' prefix
    asort($repos);

    if ($request->isFormPost()) {

      $indexed = $request->getStrList('symbolIndexLanguages');
      $indexed = array_map('strtolower', $indexed);
      $project->setSymbolIndexLanguages($indexed);

      $project->setSymbolIndexProjects($request->getArr('symbolIndexProjects'));

      $repo_id = $request->getInt('repository', 0);
      if (isset($repos[$repo_id])) {
        $project->setRepositoryID($repo_id);
        $project->save();

        return id(new AphrontRedirectResponse())
          ->setURI('/repository/');
      }
    }

    $langs = $project->getSymbolIndexLanguages();
    if ($langs) {
      $langs = implode(', ', $langs);
    } else {
      $langs = null;
    }

    if ($project->getSymbolIndexProjects()) {
      $uses = $project->getSymbolIndexProjects();
    } else {
      $uses = array();
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Name'))
          ->setValue($project->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('PHID')
          ->setValue($project->getPHID()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Repository'))
          ->setOptions($repos)
          ->setName('repository')
          ->setValue($project->getRepositoryID()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Indexed Languages'))
          ->setName('symbolIndexLanguages')
          ->setCaption(pht(
            'Separate with commas, for example: %s',
            phutil_tag('tt', array(), 'php, py')))
          ->setValue($langs))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Uses Symbols From'))
          ->setName('symbolIndexProjects')
          ->setDatasource(new DiffusionArcanistProjectDatasource())
          ->setValue($uses))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/repository/')
          ->setValue('Save'));

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Edit Arcanist Project'));
    $panel->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Project'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $panel,
      ),
      array(
        'title' => pht('Edit Project'),
      ));
  }

}
