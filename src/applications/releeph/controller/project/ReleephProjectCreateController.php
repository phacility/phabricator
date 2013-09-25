<?php

final class ReleephProjectCreateController extends ReleephProjectController {

  public function processRequest() {
    $request = $this->getRequest();
    $name = trim($request->getStr('name'));
    $trunk_branch = trim($request->getStr('trunkBranch'));
    $arc_pr_id = $request->getInt('arcPrID');

    $arc_projects = $this->loadArcProjects();

    $e_name = true;
    $e_trunk_branch = true;
    $errors = array();

    if ($request->isFormPost()) {
      if (!$name) {
        $e_name = pht('Required');
        $errors[] = pht(
          'Your Releeph project should have a simple descriptive name.');
      }

      if (!$trunk_branch) {
        $e_trunk_branch = pht('Required');
        $errors[] = pht(
          'You must specify which branch you will be picking from.');
      }

      $arc_project = $arc_projects[$arc_pr_id];
      $pr_repository = $arc_project->loadRepository();

      if (!$errors) {
        $releeph_project = id(new ReleephProject())
          ->setName($name)
          ->setTrunkBranch($trunk_branch)
          ->setRepositoryPHID($pr_repository->getPHID())
          ->setArcanistProjectID($arc_project->getID())
          ->setCreatedByUserPHID($request->getUser()->getPHID())
          ->setIsActive(1);

        try {
          $releeph_project->save();

          return id(new AphrontRedirectResponse())
            ->setURI($releeph_project->getURI());
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_name = pht('Not Unique');
          $errors[] = pht(
            'Another project already uses this name.');
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
    }

    $arc_project_options = $this->getArcProjectSelectOptions($arc_projects);

    $project_name_input = id(new AphrontFormTextControl())
      ->setLabel(pht('Name'))
      ->setDisableAutocomplete(true)
      ->setName('name')
      ->setValue($name)
      ->setError($e_name)
      ->setCaption(pht('A name like "Thrift" but not "Thrift releases".'));

    $arc_project_input = id(new AphrontFormSelectControl())
      ->setLabel(pht('Arc Project'))
      ->setName('arcPrID')
      ->setValue($arc_pr_id)
      ->setCaption(pht(
        'If your Arc project isn\'t listed, associate it with a repository %s',
        phutil_tag(
          'a',
          array(
            'href' => '/repository/',
            'target' => '_blank',
          ),
          'here')))
      ->setOptions($arc_project_options);

    $branch_name_preview = id(new ReleephBranchPreviewView())
      ->setLabel(pht('Example Branch'))
      ->addControl('projectName', $project_name_input)
      ->addControl('arcProjectID', $arc_project_input)
      ->addStatic('template', '')
      ->addStatic('isSymbolic', false);

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild($project_name_input)
      ->appendChild($arc_project_input)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Trunk'))
          ->setName('trunkBranch')
          ->setValue($trunk_branch)
          ->setError($e_trunk_branch)
          ->setCaption(pht('The development branch, '.
              'from which requests will be picked.')))
      ->appendChild($branch_name_preview)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/releeph/project/')
          ->setValue(pht('Create')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create New Project'))
      ->setFormError($error_view)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('New Project')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => pht('Create New Project'),
        'device' => true,
      ));
  }

  private function loadArcProjects() {
    $viewer = $this->getRequest()->getUser();

    $projects = id(new PhabricatorRepositoryArcanistProjectQuery())
      ->setViewer($viewer)
      ->needRepositories(true)
      ->execute();

    $projects = mfilter($projects, 'getRepository');
    $projects = msort($projects, 'getName');

    return $projects;
  }

  private function getArcProjectSelectOptions(array $arc_projects) {
    assert_instances_of($arc_projects, 'PhabricatorRepositoryArcanistProject');

    $repos = mpull($arc_projects, 'getRepository');
    $repos = mpull($repos, null, 'getID');

    $groups = array();
    foreach ($arc_projects as $arc_project) {
      $id = $arc_project->getID();
      $repo_id = $arc_project->getRepository()->getID();
      $groups[$repo_id][$id] = $arc_project->getName();
    }

    $choices = array();
    foreach ($groups as $repo_id => $group) {
      $repo_name = $repos[$repo_id]->getName();
      $callsign = $repos[$repo_id]->getCallsign();
      $name = "r{$callsign} ({$repo_name})";
      $choices[$name] = $group;
    }

    ksort($choices);

    return $choices;
  }

}
