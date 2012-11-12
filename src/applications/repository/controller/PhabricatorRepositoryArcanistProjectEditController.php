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

    $repositories = id(new PhabricatorRepository())->loadAll();
    $repos = array(
      0 => 'None',
    );
    foreach ($repositories as $repository) {
      $callsign = $repository->getCallsign();
      $name = $repository->getname();
      $repos[$repository->getID()] = "r{$callsign} ({$name})";
    }

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
      $uses = id(new PhabricatorRepositoryArcanistProject())->loadAllWhere(
        'phid in (%Ls)',
        $project->getSymbolIndexProjects());
      $uses = mpull($uses, 'getName', 'getPHID');
    } else {
      $uses = array();
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Name')
          ->setValue($project->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('PHID')
          ->setValue($project->getPHID()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Repository')
          ->setOptions($repos)
          ->setName('repository')
          ->setValue($project->getRepositoryID()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Indexed Languages')
          ->setName('symbolIndexLanguages')
          ->setCaption('Separate with commas, for example: <tt>php, py</tt>')
          ->setValue($langs))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Uses Symbols From')
          ->setName('symbolIndexProjects')
          ->setDatasource('/typeahead/common/arcanistprojects/')
          ->setValue($uses))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/repository/')
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->setHeader('Edit Arcanist Project');
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Edit Project',
      ));
  }

}
