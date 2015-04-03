<?php

final class ReleephProductEditController extends ReleephProductController {

  private $productID;

  public function willProcessRequest(array $data) {
    $this->productID = $data['projectID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $product = id(new ReleephProductQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->productID))
      ->needArcanistProjects(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }
    $this->setProduct($product);

    $e_name = true;
    $e_trunk_branch = true;
    $e_branch_template = false;
    $errors = array();

    $product_name = $request->getStr('name', $product->getName());

    $trunk_branch = $request->getStr('trunkBranch', $product->getTrunkBranch());
    $branch_template = $request->getStr('branchTemplate');
    if ($branch_template === null) {
      $branch_template = $product->getDetail('branchTemplate');
    }
    $pick_failure_instructions = $request->getStr('pickFailureInstructions',
      $product->getDetail('pick_failure_instructions'));
    $test_paths = $request->getStr('testPaths');
    if ($test_paths !== null) {
      $test_paths = array_filter(explode("\n", $test_paths));
    } else {
      $test_paths = $product->getDetail('testPaths', array());
    }

    $arc_project_id = $product->getArcanistProjectID();

    if ($request->isFormPost()) {
      $pusher_phids = $request->getArr('pushers');

      if (!$product_name) {
        $e_name = pht('Required');
        $errors[] =
          pht('Your releeph product should have a simple descriptive name.');
      }

      if (!$trunk_branch) {
        $e_trunk_branch = pht('Required');
        $errors[] =
          pht('You must specify which branch you will be picking from.');
      }

      $other_releeph_products = id(new ReleephProject())
        ->loadAllWhere('id != %d', $product->getID());
      $other_releeph_product_names = mpull($other_releeph_products,
        'getName', 'getID');

      if (in_array($product_name, $other_releeph_product_names)) {
        $errors[] = pht('Releeph product name %s is already taken',
          $product_name);
      }

      foreach ($test_paths as $test_path) {
        $result = @preg_match($test_path, '');
        $is_a_valid_regexp = $result !== false;
        if (!$is_a_valid_regexp) {
          $errors[] = pht('Please provide a valid regular expression: '.
            '%s is not valid', $test_path);
        }
      }

      $product
        ->setName($product_name)
        ->setTrunkBranch($trunk_branch)
        ->setDetail('pushers', $pusher_phids)
        ->setDetail('pick_failure_instructions', $pick_failure_instructions)
        ->setDetail('branchTemplate', $branch_template)
        ->setDetail('testPaths', $test_paths);

      $fake_commit_handle =
        ReleephBranchTemplate::getFakeCommitHandleFor($arc_project_id, $viewer);

      if ($branch_template) {
        list($branch_name, $template_errors) = id(new ReleephBranchTemplate())
          ->setCommitHandle($fake_commit_handle)
          ->setReleephProjectName($product_name)
          ->interpolate($branch_template);

        if ($template_errors) {
          $e_branch_template = pht('Whoopsies!');
          foreach ($template_errors as $template_error) {
            $errors[] = "Template error: {$template_error}";
          }
        }
      }

      if (!$errors) {
        $product->save();

        return id(new AphrontRedirectResponse())->setURI($product->getURI());
      }
    }

    $pusher_phids = $request->getArr(
      'pushers',
      $product->getDetail('pushers', array()));

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($product_name)
          ->setError($e_name)
          ->setCaption(pht('A name like "Thrift" but not "Thrift releases".')))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Repository'))
          ->setValue(
            $product->getRepository()->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Arc Project'))
          ->setValue(
            $product->getArcanistProject()->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Releeph Project PHID'))
          ->setValue(
            $product->getPHID()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Trunk'))
          ->setValue($trunk_branch)
          ->setName('trunkBranch')
          ->setError($e_trunk_branch))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Pick Instructions'))
          ->setValue($pick_failure_instructions)
          ->setName('pickFailureInstructions')
          ->setCaption(
            pht('Instructions for pick failures, which will be used '.
            'in emails generated by failed picks')))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Tests paths'))
          ->setValue(implode("\n", $test_paths))
          ->setName('testPaths')
          ->setCaption(
            pht('List of strings that all test files contain in their path '.
            'in this project. One string per line. '.
            'Examples: \'__tests__\', \'/javatests/\'...')));

    $branch_template_input = id(new AphrontFormTextControl())
      ->setName('branchTemplate')
      ->setValue($branch_template)
      ->setLabel('Branch Template')
      ->setError($e_branch_template)
      ->setCaption(
        pht("Leave this blank to use your installation's default."));

    $branch_template_preview = id(new ReleephBranchPreviewView())
      ->setLabel(pht('Preview'))
      ->addControl('template', $branch_template_input)
      ->addStatic('arcProjectID', $arc_project_id)
      ->addStatic('isSymbolic', false)
      ->addStatic('projectName', $product->getName());

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Pushers'))
          ->setName('pushers')
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setValue($pusher_phids))
      ->appendChild($branch_template_input)
      ->appendChild($branch_template_preview)
      ->appendRemarkupInstructions($this->getBranchHelpText());

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/releeph/product/')
          ->setValue(pht('Save')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Releeph Product'))
      ->setFormErrors($errors)
      ->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Product'));

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => pht('Edit Releeph Product'),
        'device' => true,
      ));
  }

  private function getBranchHelpText() {
    return <<<EOTEXT

==== Interpolations ====

| Code  | Meaning
| ----- | -------
| `%P`  | The name of your product, with spaces changed to "-".
| `%p`  | Like %P, but all lowercase.
| `%Y`  | The four digit year associated with the branch date.
| `%m`  | The two digit month.
| `%d`  | The two digit day.
| `%v`  | The handle of the commit where the branch was cut ("rXYZa4b3c2d1").
| `%V`  | The abbreviated commit id where the branch was cut ("a4b3c2d1").
| `%..` | Any other sequence interpreted by `strftime()`.
| `%%`  | A literal percent sign.


==== Tips for Branch Templates ====

Use a directory to separate your release branches from other branches:

  lang=none
  releases/%Y-%M-%d-%v
  => releases/2012-30-16-rHERGE32cd512a52b7

Include a second hierarchy if you share your repository with other products:

  lang=none
  releases/%P/%p-release-%Y%m%d-%V
  => releases/Tintin/tintin-release-20121116-32cd512a52b7

Keep your branch names simple, avoiding strange punctuation, most of which is
forbidden or escaped anyway:

  lang=none, counterexample
  releases//..clown-releases..//`date --iso=seconds`-$(sudo halt)

Include the date early in your template, in an order which sorts properly:

  lang=none
  releases/%Y%m%d-%v
  => releases/20121116-rHERGE32cd512a52b7 (good!)

  releases/%V-%m.%d.%Y
  => releases/32cd512a52b7-11.16.2012 (awful!)


EOTEXT;
  }

}
