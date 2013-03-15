<?php

final class ReleephProjectCreateController extends ReleephController {

  public function processRequest() {
    $request = $this->getRequest();
    $name = trim($request->getStr('name'));
    $trunk_branch = trim($request->getStr('trunkBranch'));
    $arc_pr_id = $request->getInt('arcPrID');


    // Only allow arc projects with repositories.  Sort and re-key by ID.
    $arc_projects = id(new PhabricatorRepositoryArcanistProject())->loadAll();
    $arc_projects = mpull(
      msort(
        mfilter($arc_projects, 'getRepositoryID'),
        'getName'),
      null,
      'getID');

    $e_name = true;
    $e_trunk_branch = true;
    $errors = array();

    if ($request->isFormPost()) {
      if (!$name) {
        $e_name = 'Required';
        $errors[] =
          'Your releeph project should have a simple descriptive name.';
      }

      if (!$trunk_branch) {
        $e_trunk_branch = 'Required';
        $errors[] =
          'You must specify which branch you will be picking from.';
      }

      $all_names = mpull(id(new ReleephProject())->loadAll(), 'getName');

      if (in_array($name, $all_names)) {
        $errors[] = "Releeph project name {$name} is already taken";
      }

      $arc_project = $arc_projects[$arc_pr_id];
      $pr_repository = $arc_project->loadRepository();

      if (!$errors) {
        $releeph_project = id(new ReleephProject())
          ->setName($name)
          ->setTrunkBranch($trunk_branch)
          ->setRepositoryID($pr_repository->getID())
          ->setRepositoryPHID($pr_repository->getPHID())
          ->setArcanistProjectID($arc_project->getID())
          ->setCreatedByUserPHID($request->getUser()->getPHID())
          ->setIsActive(1)
          ->save();

        return id(new AphrontRedirectResponse())->setURI('/releeph/');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    }

    // Make our own optgroup select control
    $arc_project_choices = array();
    $pr_repositories = mpull(
      msort(
        array_filter(
          // Some arc-projects don't have repositories
          mpull($arc_projects, 'loadRepository')),
        'getName'),
      null,
      'getID');

    foreach ($pr_repositories as $pr_repo_id => $pr_repository) {
      $options = array();
      foreach ($arc_projects as $arc_project) {
        if ($arc_project->getRepositoryID() == $pr_repo_id) {
          $options[$arc_project->getID()] = $arc_project->getName();
        }
      }
      $arc_project_choices[$pr_repository->getName()] = $options;
    }

    $project_name_input = id(new AphrontFormTextControl())
      ->setLabel('Name')
      ->setDisableAutocomplete(true)
      ->setName('name')
      ->setValue($name)
      ->setError($e_name)
      ->setCaption('A name like "Thrift" but not "Thrift releases".');

    $arc_project_input = id(new AphrontFormSelectControl())
      ->setLabel('Arc Project')
      ->setName('arcPrID')
      ->setValue($arc_pr_id)
      ->setCaption(hsprintf(
        "If your Arc project isn't listed, associate it with a repository %s",
        phutil_tag(
          'a',
          array(
            'href' => '/repository/',
            'target' => '_blank',
          ),
          'here')))
      ->setOptions($arc_project_choices);

    $branch_name_preview = id(new ReleephBranchPreviewView())
      ->setLabel('Example Branch')
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
          ->setLabel('Trunk')
          ->setName('trunkBranch')
          ->setValue($trunk_branch)
          ->setError($e_trunk_branch)
          ->setCaption('The development branch, '.
              'from which requests will be picked.'))
      ->appendChild($branch_name_preview)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/releeph/project/')
          ->setValue('Create'));

    $panel = id(new AphrontPanelView())
      ->setHeader('Create Releeph Project')
      ->appendChild($form)
      ->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array(
        'title' => 'Create new Releeph Project'
      ));
  }
}
