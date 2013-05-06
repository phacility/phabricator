<?php

final class ReleephProjectEditController extends ReleephController {

  public function processRequest() {
    $request = $this->getRequest();

    $e_name = true;
    $e_trunk_branch = true;
    $e_branch_template = false;
    $errors = array();

    $project_name = $request->getStr('name',
      $this->getReleephProject()->getName());

    $phabricator_project_id = $request->getInt('projectID',
      $this->getReleephProject()->getProjectID());
    $trunk_branch = $request->getStr('trunkBranch',
      $this->getReleephProject()->getTrunkBranch());
    $branch_template = $request->getStr('branchTemplate');
    if ($branch_template === null) {
      $branch_template =
        $this->getReleephProject()->getDetail('branchTemplate');
    }
    $pick_failure_instructions = $request->getStr('pickFailureInstructions',
      $this->getReleephProject()->getDetail('pick_failure_instructions'));
    $commit_author = $request->getStr('commitWithAuthor',
      $this->getReleephProject()->getDetail('commitWithAuthor'));
    $test_paths = $request->getStr('testPaths');
    if ($test_paths !== null) {
      $test_paths = array_filter(explode("\n", $test_paths));
    } else {
      $test_paths = $this->getReleephProject()->getDetail('testPaths', array());
    }

    $field_selector = $request->getStr('fieldSelector',
      get_class($this->getReleephProject()->getReleephFieldSelector()));

    $release_counter = $request->getInt(
      'releaseCounter',
      $this->getReleephProject()->getCurrentReleaseNumber());

    $arc_project_id = $this->getReleephProject()->getArcanistProjectID();

    if ($request->isFormPost()) {
      $pusher_phids = $request->getArr('pushers');

      if (!$project_name) {
        $e_name = pht('Required');
        $errors[] =
          pht('Your releeph project should have a simple descriptive name');
      }

      if (!$trunk_branch) {
        $e_trunk_branch = pht('Required');
        $errors[] =
          pht('You must specify which branch you will be picking from.');
      }

      if ($release_counter && !is_int($release_counter)) {
        $errors[] = pht("Release counter must be a positive integer!");
      }

      $other_releeph_projects = id(new ReleephProject())
        ->loadAllWhere('id <> %d', $this->getReleephProject()->getID());
      $other_releeph_project_names = mpull($other_releeph_projects,
        'getName', 'getID');

      if (in_array($project_name, $other_releeph_project_names)) {
        $errors[] = pht("Releeph project name %s is already taken",
          $project_name);
      }

      foreach ($test_paths as $test_path) {
        $result = @preg_match($test_path, '');
        $is_a_valid_regexp = $result !== false;
        if (!$is_a_valid_regexp) {
          $errors[] = pht('Please provide a valid regular expression: '.
            '%s is not valid', $test_path);
        }
      }

      $project = $this->getReleephProject()
        ->setProjectID($phabricator_project_id)
        ->setTrunkBranch($trunk_branch)
        ->setDetail('pushers', $pusher_phids)
        ->setDetail('pick_failure_instructions', $pick_failure_instructions)
        ->setDetail('field_selector', $field_selector)
        ->setDetail('branchTemplate', $branch_template)
        ->setDetail('commitWithAuthor', $commit_author)
        ->setDetail('testPaths', $test_paths);

      if ($release_counter) {
        $project->setDetail('releaseCounter', $release_counter);
      }

      $fake_commit_handle =
        ReleephBranchTemplate::getFakeCommitHandleFor($arc_project_id);

      if ($branch_template) {
        list($branch_name, $template_errors) = id(new ReleephBranchTemplate())
          ->setCommitHandle($fake_commit_handle)
          ->setReleephProjectName($project_name)
          ->interpolate($branch_template);

        if ($template_errors) {
          $e_branch_template = pht('Whoopsies!');
          foreach ($template_errors as $template_error) {
            $errors[] = "Template error: {$template_error}";
          }
        }
      }

      if (!$errors) {
        $project->save();

        return id(new AphrontRedirectResponse())
          ->setURI('/releeph/project/');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle(pht('Form Errors'));
    }

    $projects = mpull(
      id(new PhabricatorProject())->loadAll(),
      'getName',
      'getID');

    $projects[0] = '-'; // no project associated, that's ok

    $pusher_phids = $request->getArr(
      'pushers',
      $this->getReleephProject()->getDetail('pushers', array()));

    $handles = id(new PhabricatorObjectHandleData($pusher_phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $pusher_tokens = array();
    foreach ($pusher_phids as $phid) {
      $pusher_tokens[$phid] = $handles[$phid]->getFullName();
    }

    $basic_inset = id(new AphrontFormInsetView())
      ->setTitle(pht('Basics'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($project_name)
          ->setError($e_name)
          ->setCaption(pht('A name like "Thrift" but not "Thrift releases".')))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Repository'))
          ->setValue(
              $this
                ->getReleephProject()
                ->loadPhabricatorRepository()
                ->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Arc Project'))
          ->setValue(
              $this->getReleephProject()->loadArcanistProject()->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Releeph Project PHID'))
          ->setValue(
              $this->getReleephProject()->getPHID()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Phabricator Project'))
          ->setValue($phabricator_project_id)
          ->setName('projectID')
          ->setOptions($projects))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Trunk'))
          ->setValue($trunk_branch)
          ->setName('trunkBranch')
          ->setError($e_trunk_branch))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Release counter'))
          ->setValue($release_counter)
          ->setName('releaseCounter')
          ->setCaption(
            pht("Used by the command line branch cutter's %N field")))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Pick Instructions'))
          ->setValue($pick_failure_instructions)
          ->setName('pickFailureInstructions')
          ->setCaption(
            pht("Instructions for pick failures, which will be used " .
            "in emails generated by failed picks")))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Tests paths'))
          ->setValue(implode("\n", $test_paths))
          ->setName('testPaths')
          ->setCaption(
            pht('List of strings that all test files contain in their path '.
            'in this project. One string per line. '.
            'Examples: \'__tests__\', \'/javatests/\'...')));

    $pushers_inset = id(new AphrontFormInsetView())
      ->setTitle(pht('Pushers'))
      ->appendChild(
        pht('Pushers are allowed to approve Releeph requests to be committed. '.
        'to this project\'s branches.  If you leave this blank then anyone '.
        'is allowed to approve requests.'))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Pushers'))
          ->setName('pushers')
          ->setDatasource('/typeahead/common/users/')
          ->setValue($pusher_tokens));

    $field_selector_options = array();
    $field_selector_symbols = id(new PhutilSymbolLoader())
      ->setType('class')
      ->setConcreteOnly(true)
      ->setAncestorClass('ReleephFieldSelector')
      ->selectAndLoadSymbols();
    foreach ($field_selector_symbols as $symbol) {
      $selector_name = $symbol['name'];
      $field_selector_options[$selector_name] = $selector_name;
    }

    $field_selector_blurb = pht(
        "If you you have additional information to render about Releeph ".
        "requests, or want to re-arrange the UI, implement a ".
        "<tt>ReleephFieldSelector</tt> and select it here.");

    $fields_inset = id(new AphrontFormInsetView())
      ->setTitle(pht('Fields'))
      ->appendChild($field_selector_blurb)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Selector'))
          ->setName('fieldSelector')
          ->setValue($field_selector)
          ->setOptions($field_selector_options));

    $commit_author_inset = $this->buildCommitAuthorInset($commit_author);

    // Build the Template inset
    $markup_engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();

    // From DifferentialUnitFieldSpecification...
    $markup_engine->setConfig('viewer', $request->getUser());

    $help_markup = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      phutil_safe_html(
        $markup_engine->markupText(ReleephBranchTemplate::getHelpRemarkup())));

    $branch_template_input = id(new AphrontFormTextControl())
      ->setName('branchTemplate')
      ->setValue($branch_template)
      ->setLabel('Template')
      ->setError($e_branch_template)
      ->setCaption(
        pht("Leave this blank to use your installation's default."));

    $branch_template_preview = id(new ReleephBranchPreviewView())
      ->setLabel(pht('Preview'))
      ->addControl('template', $branch_template_input)
      ->addStatic('arcProjectID', $arc_project_id)
      ->addStatic('isSymbolic', false)
      ->addStatic('projectName', $this->getReleephProject()->getName());

    $template_inset = id(new AphrontFormInsetView())
      ->setTitle(pht('Branch Cutting'))
      ->appendChild(
        pht('Provide a pattern for creating new branches.'))
      ->appendChild($branch_template_input)
      ->appendChild($branch_template_preview)
      ->appendChild($help_markup);

    // Build the form
    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild($basic_inset)
      ->appendChild($pushers_inset)
      ->appendChild($fields_inset)
      ->appendChild($commit_author_inset)
      ->appendChild($template_inset);

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/releeph/project/')
          ->setValue(pht('Save')));

    $panel = id(new AphrontPanelView())
      ->setHeader(pht('Edit Releeph Project'))
      ->appendChild($form)
      ->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array('title' => pht('Edit Releeph Project')));
  }

  private function buildCommitAuthorInset($current) {
    $vcs_type = $this->getReleephProject()
      ->loadPhabricatorRepository()
      ->getVersionControlSystem();

    switch ($vcs_type) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        break;

      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        return;
        break;
    }

    $vcs_name = PhabricatorRepositoryType::getNameForRepositoryType($vcs_type);

    // pht?
    $help_markup = hsprintf(<<<EOTEXT
When your project's release engineers run <tt>arc releeph</tt>, they will be
listed as the <strong>committer</strong> of the code committed to release
branches.

%s allows you to specify a separate author when committing code.  Some
tools use the author of a commit (rather than the committer) when they need to
notify someone about a build or test failure.

Releeph can use one of the following to set the <strong>author</strong> of the
commits it makes:
EOTEXT
    , $vcs_name);

    $trunk = $this->getReleephProject()->getTrunkBranch();

    $options = array(
      array(
        'value'   => ReleephProject::COMMIT_AUTHOR_FROM_DIFF,
        'label'   => pht('Original Author'),
        'caption' =>
          pht('The author of the original commit in: %s.', $trunk),
      ),
      array(
        'value'   => ReleephProject::COMMIT_AUTHOR_REQUESTOR,
        'label'   => pht('Requestor'),
        'caption' =>
          pht('The person who requested that this code go into the release.'),
      ),
      array(
        'value'   => ReleephProject::COMMIT_AUTHOR_NONE,
        'label'   => pht('None'),
        'caption' =>
          pht('Only record the default committer information.'),
      ),
    );

    if (!$current) {
      $current = ReleephProject::COMMIT_AUTHOR_FROM_DIFF;
    }

    $control = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Author'))
      ->setName('commitWithAuthor')
      ->setValue($current);

    foreach ($options as $dict) {
      $control->addButton($dict['value'], $dict['label'], $dict['caption']);
    }

    return id(new AphrontFormInsetView())
      ->setTitle(pht('Authors'))
      ->appendChild($help_markup)
      ->appendChild($control);
  }

}
