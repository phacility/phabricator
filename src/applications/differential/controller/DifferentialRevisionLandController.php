<?php

final class DifferentialRevisionLandController extends DifferentialController {

  private $revisionID;
  private $strategyClass;
  private $pushStrategy;

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
    $this->strategyClass = $data['strategy'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $revision_id = $this->revisionID;

    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($revision_id))
      ->setViewer($viewer)
      ->executeOne();
    if (!$revision) {
      return new Aphront404Response();
    }

    if (is_subclass_of($this->strategyClass, 'DifferentialLandingStrategy')) {
      $this->pushStrategy = newv($this->strategyClass, array());
    } else {
      throw new Exception(
        "Strategy type must be a valid class name and must subclass ".
        "DifferentialLandingStrategy. ".
        "'{$this->strategyClass}' is not a subclass of ".
        "DifferentialLandingStrategy.");
    }

    if ($request->isDialogFormPost()) {
      $response = null;
      $text = '';
      try {
        $response = $this->attemptLand($revision, $request);
        $title = pht('Success!');
        $text = pht('Revision was successfully landed.');
      } catch (Exception $ex) {
        $title = pht('Failed to land revision');
        if ($ex instanceof PhutilProxyException) {
          $text = hsprintf(
            '%s:<br><pre>%s</pre>',
            $ex->getMessage(),
            $ex->getPreviousException()->getMessage());
        } else {
          $text = phutil_tag('pre', array(), $ex->getMessage());
        }
        $text = id(new PHUIInfoView())
           ->appendChild($text);
      }

      if ($response instanceof AphrontDialogView) {
        $dialog = $response;
      } else {
        $dialog = id(new AphrontDialogView())
          ->setUser($viewer)
          ->setTitle($title)
          ->appendChild(phutil_tag('p', array(), $text))
          ->addCancelButton('/D'.$revision_id, pht('Done'));
      }
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $is_disabled = $this->pushStrategy->isActionDisabled(
      $viewer,
      $revision,
      $revision->getRepository());
    if ($is_disabled) {
      if (is_string($is_disabled)) {
        $explain = $is_disabled;
      } else {
        $explain = pht('This action is not currently enabled.');
      }
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht("Can't land revision"))
        ->appendChild($explain)
        ->addCancelButton('/D'.$revision_id);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }


    $prompt = hsprintf('%s<br><br>%s',
      pht(
        'This will squash and rebase revision %s, and push it to '.
          'the default / master branch.',
        $revision_id),
      pht('It is an experimental feature and may not work.'));

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Land Revision %s?', $revision_id))
      ->appendChild($prompt)
      ->setSubmitURI($request->getRequestURI())
      ->addSubmitButton(pht('Land it!'))
      ->addCancelButton('/D'.$revision_id);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function attemptLand($revision, $request) {
    $status = $revision->getStatus();
    if ($status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      throw new Exception('Only Accepted revisions can be landed.');
    }

    $repository = $revision->getRepository();

    if ($repository === null) {
      throw new Exception('revision is not attached to a repository.');
    }

    $can_push = PhabricatorPolicyFilter::hasCapability(
      $request->getUser(),
      $repository,
      DiffusionPushCapability::CAPABILITY);

    if (!$can_push) {
      throw new Exception(
        pht('You do not have permission to push to this repository.'));
    }

    $lock = $this->lockRepository($repository);

    try {
      $response = $this->pushStrategy->processLandRequest(
        $request,
        $revision,
        $repository);
    } catch (Exception $e) {
      $lock->unlock();
      throw $e;
    }

    $lock->unlock();

    $looksoon = new ConduitCall(
      'diffusion.looksoon',
      array(
        'callsigns' => array($repository->getCallsign()),
      ));
    $looksoon->setUser($request->getUser());
    $looksoon->execute();

    return $response;
  }

  private function lockRepository($repository) {
    $lock_name = __CLASS__.':'.($repository->getCallsign());
    $lock = PhabricatorGlobalLock::newLock($lock_name);
    $lock->lock();
    return $lock;
  }

}
