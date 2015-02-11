<?php

final class PhragmentSnapshotPromoteController extends PhragmentController {

  private $dblob;
  private $id;
  private $targetSnapshot;
  private $targetFragment;
  private $snapshots;
  private $options;

  public function willProcessRequest(array $data) {
    $this->dblob = idx($data, 'dblob', null);
    $this->id = idx($data, 'id', null);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    // When the user is promoting a snapshot to the latest version, the
    // identifier is a fragment path.
    if ($this->dblob !== null) {
      $this->targetFragment = id(new PhragmentFragmentQuery())
        ->setViewer($viewer)
        ->requireCapabilities(array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
        ->withPaths(array($this->dblob))
        ->executeOne();
      if ($this->targetFragment === null) {
        return new Aphront404Response();
      }

      $this->snapshots = id(new PhragmentSnapshotQuery())
        ->setViewer($viewer)
        ->withPrimaryFragmentPHIDs(array($this->targetFragment->getPHID()))
        ->execute();
    }

    // When the user is promoting a snapshot to another snapshot, the
    // identifier is another snapshot ID.
    if ($this->id !== null) {
      $this->targetSnapshot = id(new PhragmentSnapshotQuery())
        ->setViewer($viewer)
        ->requireCapabilities(array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
        ->withIDs(array($this->id))
        ->executeOne();
      if ($this->targetSnapshot === null) {
        return new Aphront404Response();
      }

      $this->snapshots = id(new PhragmentSnapshotQuery())
        ->setViewer($viewer)
        ->withPrimaryFragmentPHIDs(array(
          $this->targetSnapshot->getPrimaryFragmentPHID(),
        ))
        ->execute();
    }

    // If there's no identifier, just 404.
    if ($this->snapshots === null) {
      return new Aphront404Response();
    }

    // Work out what options the user has.
    $this->options = mpull(
      $this->snapshots,
      'getName',
      'getID');
    if ($this->id !== null) {
      unset($this->options[$this->id]);
    }

    // If there's no options, show a dialog telling the
    // user there are no snapshots to promote.
    if (count($this->options) === 0) {
      return id(new AphrontDialogResponse())->setDialog(
        id(new AphrontDialogView())
          ->setTitle(pht('No snapshots to promote'))
          ->appendParagraph(pht(
            'There are no snapshots available to promote.'))
          ->setUser($request->getUser())
          ->addCancelButton(pht('Cancel')));
    }

    // Handle snapshot promotion.
    if ($request->isDialogFormPost()) {
      $snapshot = id(new PhragmentSnapshotQuery())
        ->setViewer($viewer)
        ->withIDs(array($request->getStr('snapshot')))
        ->executeOne();
      if ($snapshot === null) {
        return new Aphront404Response();
      }

      $snapshot->openTransaction();
        // Delete all existing child entries.
        $children = id(new PhragmentSnapshotChildQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withSnapshotPHIDs(array($snapshot->getPHID()))
          ->execute();
        foreach ($children as $child) {
          $child->delete();
        }

        if ($this->id === null) {
          // The user is promoting the snapshot to the latest version.
          $children = id(new PhragmentFragmentQuery())
            ->setViewer($viewer)
            ->needLatestVersion(true)
            ->withLeadingPath($this->targetFragment->getPath().'/')
            ->execute();

          // Add the primary fragment.
          id(new PhragmentSnapshotChild())
            ->setSnapshotPHID($snapshot->getPHID())
            ->setFragmentPHID($this->targetFragment->getPHID())
            ->setFragmentVersionPHID(
              $this->targetFragment->getLatestVersionPHID())
            ->save();

          // Add all of the child fragments.
          foreach ($children as $child) {
            id(new PhragmentSnapshotChild())
              ->setSnapshotPHID($snapshot->getPHID())
              ->setFragmentPHID($child->getPHID())
              ->setFragmentVersionPHID($child->getLatestVersionPHID())
              ->save();
          }
        } else {
          // The user is promoting the snapshot to another snapshot. We just
          // copy the other snapshot's child entries and change the snapshot
          // PHID to make it identical.
          $children = id(new PhragmentSnapshotChildQuery())
            ->setViewer($viewer)
            ->withSnapshotPHIDs(array($this->targetSnapshot->getPHID()))
            ->execute();
          foreach ($children as $child) {
            id(new PhragmentSnapshotChild())
              ->setSnapshotPHID($snapshot->getPHID())
              ->setFragmentPHID($child->getFragmentPHID())
              ->setFragmentVersionPHID($child->getFragmentVersionPHID())
              ->save();
          }
        }
      $snapshot->saveTransaction();

      if ($this->id === null) {
        return id(new AphrontRedirectResponse())
          ->setURI($this->targetFragment->getURI());
      } else {
        return id(new AphrontRedirectResponse())
          ->setURI($this->targetSnapshot->getURI());
      }
    }

    return $this->createDialog();
  }

  public function createDialog() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setTitle(pht('Promote which snapshot?'))
      ->setUser($request->getUser())
      ->addSubmitButton(pht('Promote'))
      ->addCancelButton(pht('Cancel'));

    if ($this->id === null) {
      // The user is promoting a snapshot to the latest version.
      $dialog->appendParagraph(pht(
        'Select the snapshot you want to promote to the latest version:'));
    } else {
      // The user is promoting a snapshot to another snapshot.
      $dialog->appendParagraph(pht(
        "Select the snapshot you want to promote to '%s':",
        $this->targetSnapshot->getName()));
    }

    $dialog->appendChild(
      id(new AphrontFormSelectControl())
        ->setUser($viewer)
        ->setName('snapshot')
        ->setOptions($this->options));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
