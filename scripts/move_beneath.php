#!/usr/bin/env php
<?php

// See <https://secure.phabricator.com/T10350> for discussion.

require_once 'scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'  => 'milestone',
      'help'  => pht(
        'Turn the project into a milestone. Or, use --subproject.'),
    ),
    array(
      'name' => 'child',
      'param' => 'project',
      'help' => pht('The project to make a child of the --parent project.'),
    ),
    array(
      'name' => 'parent',
      'param' => 'project',
      'help' => pht('The project to make a parent of the --child project.'),
    ),
    array(
      'name' => 'subproject',
      'help' => pht(
        'Turn the project into a subproject. Or, use --milestone.'),
    ),
    array(
      'name' => 'keep-members',
      'param' => 'mode',
      'help' => pht('Choose which members to keep: both, child, parent.'),
    ),
  ));


$parent_name = $args->getArg('parent');
$child_name = $args->getArg('child');

if (!$parent_name) {
  throw new PhutilArgumentUsageException(
    pht(
      'Choose which project should become the parent with --parent.'));
}

if (!$child_name) {
  throw new PhutilArgumentUsageException(
    pht(
      'Choose which project should become the child with --child.'));
}

$keep_members = $args->getArg('keep-members');
switch ($keep_members) {
  case 'both':
  case 'child':
  case 'parent':
    break;
  default:
    if (!$keep_members) {
      throw new PhutilArgumentUsageException(
        pht(
          'Choose which members to keep with --keep-members.'));
    } else {
      throw new PhutilArgumentUsageException(
        pht(
          'Valid --keep-members settings are: both, child, parent.'));
    }
}

$want_milestone = $args->getArg('milestone');
$want_subproject = $args->getArg('subproject');
if (!$want_milestone && !$want_subproject) {
  throw new PhutilArgumentUsageException(
    pht(
      'Use --milestone or --subproject to select what kind of child the '.
      'project should become.'));
} else if ($want_milestone && $want_subproject) {
  throw new PhutilArgumentUsageException(
    pht(
      'Use either --milestone or --subproject, not both, to select what kind '.
      'of project the child should become.'));
}
$is_milestone = $want_milestone;

$parent = load_project($parent_name);
$child = load_project($child_name);

if ($parent->isMilestone()) {
  throw new PhutilArgumentUsageException(
    pht(
      'The selected parent project is a milestone, and milestones may '.
      'not have children.'));
}

if ($child->getParentProjectPHID()) {
  throw new PhutilArgumentUsageException(
    pht(
      'The selected child project is already a child of another project. '.
      'This script can only move root-level projects beneath other projects, '.
      'not move children within a hierarchy.'));
}

if ($child->getHasSubprojects() || $child->getHasMilestones()) {
  throw new PhutilArgumentUsageException(
    pht(
      'The selected child project already has subprojects or milestones '.
      'of its own. This script can not move entire trees of projects.'));
}

if ($parent->getPHID() == $child->getPHID()) {
  throw new PhutilArgumentUsageException(
    pht(
      'The parent and child are the same project. There is no conceivable '.
      'physical interpretation of what you are attempting to do.'));
}


if ($is_milestone) {
  if (($keep_members != 'parent') && $parent->getHasSubprojects()) {
    throw new PhutilArgumentUsageException(
      pht(
        'You can not use "child" or "both" modes when making a project a '.
        'milestone of a project with existing subprojects: there is nowhere '.
        'to put the members.'));
  }

  $copy_parent = false;
  $copy_child = ($keep_members != 'parent');
  $wipe_parent = ($keep_members == 'child');
  $wipe_child = true;
} else {
  $copy_parent = ($keep_members != 'child');
  $copy_child = false;
  $wipe_parent = true;
  $wipe_child = ($keep_members == 'parent');
}

$child->setParentProjectPHID($parent->getPHID());
$child->attachParentProject($parent);

if ($is_milestone) {
  $next_number = $parent->loadNextMilestoneNumber();
  $child->setMilestoneNumber($next_number);
}

$child->save();

$member_type = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

$parent_members = PhabricatorEdgeQuery::loadDestinationPHIDs(
  $parent->getPHID(),
  $member_type);

$child_members = PhabricatorEdgeQuery::loadDestinationPHIDs(
  $child->getPHID(),
  $member_type);

if ($copy_parent) {
  edit_members($parent_members, $child, true);
}

if ($copy_child) {
  edit_members($child_members, $parent, true);
}

if ($wipe_parent) {
  edit_members($parent_members, $parent, false);
}

if ($wipe_child) {
  edit_members($child_members, $child, false);
}

id(new PhabricatorProjectsMembershipIndexEngineExtension())
  ->rematerialize($parent);

id(new PhabricatorProjectsMembershipIndexEngineExtension())
  ->rematerialize($child);

echo tsprintf(
  "%s\n",
  pht('Done.'));


function load_project($name) {
  $viewer = PhabricatorUser::getOmnipotentUser();

  $project = id(new PhabricatorProjectQuery())
    ->setViewer($viewer)
    ->withSlugs(array($name))
    ->executeOne();
  if ($project) {
    return $project;
  }

  $project = id(new PhabricatorProjectQuery())
    ->setViewer($viewer)
    ->withPHIDs(array($name))
    ->executeOne();
  if ($project) {
    return $project;
  }

  $project = id(new PhabricatorProjectQuery())
    ->setViewer($viewer)
    ->withIDs(array($name))
    ->executeOne();
  if ($project) {
    return $project;
  }

  throw new Exception(
    pht(
      'Unknown project "%s"! Use a hashtags, PHID, or ID to choose a project.',
      $name));
}

function edit_members(array $phids, PhabricatorProject $target, $add) {
  if (!$phids) {
    return;
  }

  $member_type = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

  $editor = id(new PhabricatorEdgeEditor());
  foreach ($phids as $phid) {
    if ($add) {
      $editor->addEdge($target->getPHID(), $member_type, $phid);
    } else {
      $editor->removeEdge($target->getPHID(), $member_type, $phid);
    }
  }
  $editor->save();
}
