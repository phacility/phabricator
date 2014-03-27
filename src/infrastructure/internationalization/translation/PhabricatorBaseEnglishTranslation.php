<?php

abstract class PhabricatorBaseEnglishTranslation
  extends PhabricatorTranslation {

  final public function getLanguage() {
    return 'en';
  }

  public function getTranslations() {
    return array(
      'These %d configuration value(s) are related:' => array(
        'This configuration value is related:',
        'These configuration values are related:',
      ),
      'Differential Revision(s)' => array(
        'Differential Revision',
        'Differential Revisions',
      ),
      'file(s)' => array('file', 'files'),
      'Maniphest Task(s)' => array('Maniphest Task', 'Maniphest Tasks'),
      'Task(s)' => array('Task', 'Tasks'),

      'Please fix these errors and try again.' => array(
        'Please fix this error and try again.',
        'Please fix these errors and try again.',
      ),

      '%d Error(s)' => array('%d Error', '%d Errors'),
      '%d Warning(s)' => array('%d Warning', '%d Warnings'),
      '%d Auto-Fix(es)' => array('%d Auto-Fix', '%d Auto-Fixes'),
      '%d Advice(s)' => array('%d Advice', '%d Pieces of Advice'),
      '%d Detail(s)' => array('%d Detail', '%d Details'),

      '(%d line(s))' => array('(%d line)', '(%d lines)'),

      'COMMIT(S)' => array('COMMIT', 'COMMITS'),

      '%d line(s)' => array('%d line', '%d lines'),
      '%d path(s)' => array('%d path', '%d paths'),
      '%d diff(s)' => array('%d diff', '%d diffs'),

      'added %d commit(s): %s' => array(
        'added commit: %2$s',
        'added commits: %2$s',
      ),

      'removed %d commit(s): %s' => array(
        'removed commit: %2$s',
        'removed commits: %2$s',
      ),

      'changed %d commit(s), added %d: %s; removed %d: %s' =>
        'changed commits, added: %3$s; removed: %5$s',

      'ATTACHED %d COMMIT(S)' => array(
        'ATTACHED COMMIT',
        'ATTACHED COMMITS',
      ),

      'added %d mock(s): %s' => array(
        'added a mock: %2$s',
        'added mocks: %2$s',
      ),

      'removed %d mock(s): %s' => array(
        'removed a mock: %2$s',
        'removed mocks: %2$s',
      ),

      'changed %d mock(s), added %d: %s; removed %d: %s' =>
        'changed mocks, added: %3$s; removed: %5$s',

      'ATTACHED %d MOCK(S)' => array(
        'ATTACHED MOCK',
        'ATTACHED MOCKS',
      ),

      'added %d dependencie(s): %s' => array(
        'added dependency: %2$s',
        'added dependencies: %2$s',
      ),

      'added %d dependent task(s): %s' => array(
        'added dependent task: %2$s',
        'added dependent tasks: %2$s',
      ),

      'removed %d dependencie(s): %s' => array(
        'removed dependency: %2$s',
        'removed dependencies: %2$s',
      ),

      'removed %d dependent task(s): %s' => array(
        'removed dependent task: %2$s',
        'removed dependent tasks: %2$s',
      ),

      'changed %d dependencie(s), added %d: %s; removed %d: %s' =>
        'changed dependencies, added: %3$s; removed: %5$s',

      'changed %d dependent task(s), added %d: %s; removed %d: %s',
        'changed dependent tasks, added: %3$s; removed: %5$s',

      'DEPENDENT %d TASK(s)' => array(
        'DEPENDENT TASK',
        'DEPENDENT TASKS',
      ),

      'DEPENDS ON %d TASK(S)' => array(
        'DEPENDS ON TASK',
        'DEPENDS ON TASKS',
      ),

      'DIFFERENTIAL %d REVISION(S)' => array(
        'DIFFERENTIAL REVISION',
        'DIFFERENTIAL REVISIONS',
      ),

      'added %d revision(s): %s' => array(
        'added revision: %2$s',
        'added revisions: %2$s',
      ),

      'removed %d revision(s): %s' => array(
        'removed revision: %2$s',
        'removed revisions: %2$s',
      ),

      'changed %d revision(s), added %d: %s; removed %d: %s' =>
        'changed revisions, added %3$s; removed %5$s',

      '%s edited revision(s), added %d: %s; removed %d: %s.' =>
        '%s edited revisions, added: %3$s; removed: %5$s',

      'There are %d raw fact(s) in storage.' => array(
        'There is %d raw fact in storage.',
        'There are %d raw facts in storage.',
      ),

      'There are %d aggregate fact(s) in storage.' => array(
        'There is %d aggregate fact in storage.',
        'There are %d aggregate facts in storage.',
      ),

      '%d Commit(s) Awaiting Audit' => array(
        '%d Commit Awaiting Audit',
        '%d Commits Awaiting Audit',
      ),

      '%d Problem Commit(s)' => array(
        '%d Problem Commit',
        '%d Problem Commits',
      ),

      '%d Review(s) Blocking Others' => array(
        '%d Review Blocking Others',
        '%d Reviews Blocking Others',
      ),

      '%d Review(s) Need Attention' => array(
        '%d Review Needs Attention',
        '%d Reviews Need Attention',
      ),

      '%d Review(s) Waiting on Others' => array(
        '%d Review Waiting on Others',
        '%d Reviews Waiting on Others',
      ),

      '%d Flagged Object(s)' => array(
        '%d Flagged Object',
        '%d Flagged Objects',
      ),

      '%d Unbreak Now Task(s)!' => array(
        '%d Unbreak Now Task!',
        '%d Unbreak Now Tasks!',
      ),

      '%d Assigned Task(s)' => array(
        '%d Assigned Task',
        '%d Assigned Tasks',
      ),

      'Show %d Lint Message(s)' => array(
        'Show %d Lint Message',
        'Show %d Lint Messages',
      ),
      'Hide %d Lint Message(s)' => array(
        'Hide %d Lint Message',
        'Hide %d Lint Messages',
      ),
      'Switch for %d Lint Message(s)' => array(
        'Switch for %d Lint Message',
        'Switch for %d Lint Messages',
      ),
      '%d Lint Message(s)' => array(
        '%d Lint Message',
        '%d Lint Messages',
      ),

      'This is a binary file. It is %s byte(s) in length.' => array(
        'This is a binary file. It is %s byte in length.',
        'This is a binary file. It is %s bytes in length.',
      ),

      '%d Action(s) Have No Effect' => array(
        'Action Has No Effect',
        'Actions Have No Effect',
      ),

      '%d Action(s) With No Effect' => array(
        'Action With No Effect',
        'Actions With No Effect',
      ),

      'Some of your %d action(s) have no effect:' => array(
        'One of your actions has no effect:',
        'Some of your actions have no effect:',
      ),

      'Apply remaining %d action(s)?' => array(
        'Apply remaining action?',
        'Apply remaining actions?',
      ),

      'Apply %d Other Action(s)' => array(
        'Apply Remaining Action',
        'Apply Remaining Actions',
      ),

      'The %d action(s) you are taking have no effect:' => array(
        'The action you are taking has no effect:',
        'The actions you are taking have no effect:',
      ),

      '%s edited post(s), added %d: %s; removed %d: %s.' =>
        '%s edited posts, added: %3$s; removed: %5$s',

      '%s added %d post(s): %s.' => array(
        array(
          '%s added a post: %3$s.',
          '%s added posts: %3$s.',
        ),
      ),

      '%s removed %d post(s): %s.' => array(
        array(
          '%s removed a post: %3$s.',
          '%s removed posts: %3$s.',
        ),
      ),

      '%s edited blog(s), added %d: %s; removed %d: %s.' =>
        '%s edited blogs, added: %3$s; removed: %5$s',

      '%s added %d blog(s): %s.' => array(
        array(
          '%s added a blog: %3$s.',
          '%s added blogs: %3$s.',
        ),
      ),

      '%s removed %d blog(s): %s.' => array(
        array(
          '%s removed a blog: %3$s.',
          '%s removed blogs: %3$s.',
        ),
      ),

      '%s edited blogger(s), added %d: %s; removed %d: %s.' =>
        '%s edited bloggers, added: %3$s; removed: %5$s',

      '%s added %d blogger(s): %s.' => array(
        array(
          '%s added a blogger: %3$s.',
          '%s added bloggers: %3$s.',
        ),
      ),

      '%s removed %d blogger(s): %s.' => array(
        array(
          '%s removed a blogger: %3$s.',
          '%s removed bloggers: %3$s.',
        ),
      ),

      '%s edited member(s), added %d: %s; removed %d: %s.' =>
        '%s edited members, added: %3$s; removed: %5$s',

      '%s added %d member(s): %s.' => array(
        array(
          '%s added a member: %3$s.',
          '%s added members: %3$s.',
        ),
      ),

      '%s removed %d member(s): %s.' => array(
        array(
          '%s removed a member: %3$s.',
          '%s removed members: %3$s.',
        ),
      ),

      '%s edited project(s), added %d: %s; removed %d: %s.' =>
        '%s edited projects, added: %3$s; removed: %5$s',

      '%s added %d project(s): %s.' => array(
        array(
          '%s added a project: %3$s.',
          '%s added projects: %3$s.',
        ),
      ),

      '%s removed %d project(s): %s.' => array(
        array(
          '%s removed a project: %3$s.',
          '%s removed projects: %3$s.',
        ),
      ),

      '%s changed project(s) of %s, added %d: %s; removed %d: %s' =>
        '%s changed projects of %s, added: %4$s; removed: %6$s',

      '%s added %d project(s) to %s: %s' => array(
        array(
          '%s added a project to %3$s: %4$s',
          '%s added projects to %3$s: %4$s',
        ),
      ),

      '%s removed %d project(s) from %s: %s' => array(
        array(
          '%s removed a project from %3$s: %4$s',
          '%s removed projects from %3$s: %4$s',
        ),
      ),

      '%s edited voting user(s), added %d: %s; removed %d: %s.' =>
        '%s edited voting users, added: %3$s; removed: %5$s',

      '%s added %d voting user(s): %s.' => array(
        array(
          '%s added a voting user: %3$s.',
          '%s added voting users: %3$s.',
        ),
      ),

      '%s removed %d voting user(s): %s.' => array(
        array(
          '%s removed a voting user: %3$s.',
          '%s removed voting users: %3$s.',
        ),
      ),

      '%s edited answer(s), added %d: %s; removed %d: %s.' =>
        '%s edited answers, added: %3$s; removed: %5$s',

      '%s added %d answer(s): %s.' => array(
        array(
          '%s added a answer: %3$s.',
          '%s added answers: %3$s.',
        ),
      ),

      '%s removed %d answer(s): %s.' => array(
        array(
          '%s removed a answer: %3$s.',
          '%s removed answers: %3$s.',
        ),
      ),

     '%s edited question(s), added %d: %s; removed %d: %s.' =>
        '%s edited questions, added: %3$s; removed: %5$s',

      '%s added %d question(s): %s.' => array(
        array(
          '%s added a question: %3$s.',
          '%s added questions: %3$s.',
        ),
      ),

      '%s removed %d question(s): %s.' => array(
        array(
          '%s removed a question: %3$s.',
          '%s removed questions: %3$s.',
        ),
      ),

      '%s edited mock(s), added %d: %s; removed %d: %s.' =>
        '%s edited mocks, added: %3$s; removed: %5$s',

      '%s added %d mock(s): %s.' => array(
        array(
          '%s added a mock: %3$s.',
          '%s added mocks: %3$s.',
        ),
      ),

      '%s removed %d mock(s): %s.' => array(
        array(
          '%s removed a mock: %3$s.',
          '%s removed mocks: %3$s.',
        ),
      ),

      '%s edited task(s), added %d: %s; removed %d: %s.' =>
        '%s edited tasks, added: %3$s; removed: %5$s',

      '%s added %d task(s): %s.' => array(
        array(
          '%s added a task: %3$s.',
          '%s added tasks: %3$s.',
        ),
      ),

      '%s removed %d task(s): %s.' => array(
        array(
          '%s removed a task: %3$s.',
          '%s removed tasks: %3$s.',
        ),
      ),

      '%s edited file(s), added %d: %s; removed %d: %s.' =>
        '%s edited files, added: %3$s; removed: %5$s',

      '%s added %d file(s): %s.' => array(
        array(
          '%s added a file: %3$s.',
          '%s added files: %3$s.',
        ),
      ),

      '%s removed %d file(s): %s.' => array(
        array(
          '%s removed a file: %3$s.',
          '%s removed files: %3$s.',
        ),
      ),

      '%s edited account(s), added %d: %s; removed %d: %s.' =>
        '%s edited accounts, added: %3$s; removed: %5$s',

      '%s added %d account(s): %s.' => array(
        array(
          '%s added a account: %3$s.',
          '%s added accounts: %3$s.',
        ),
      ),

      '%s removed %d account(s): %s.' => array(
        array(
          '%s removed a account: %3$s.',
          '%s removed accounts: %3$s.',
        ),
      ),

      '%s edited charge(s), added %d: %s; removed %d: %s.' =>
        '%s edited charges, added: %3$s; removed: %5$s',

      '%s added %d charge(s): %s.' => array(
        array(
          '%s added a charge: %3$s.',
          '%s added charges: %3$s.',
        ),
      ),

      '%s removed %d charge(s): %s.' => array(
        array(
          '%s removed a charge: %3$s.',
          '%s removed charges: %3$s.',
        ),
      ),

      '%s edited purchase(s), added %d: %s; removed %d: %s.' =>
        '%s edited purchases, added: %3$s; removed: %5$s',

      '%s added %d purchase(s): %s.' => array(
        array(
          '%s added a purchase: %3$s.',
          '%s added purchases: %3$s.',
        ),
      ),

      '%s removed %d purchase(s): %s.' => array(
        array(
          '%s removed a purchase: %3$s.',
          '%s removed purchases: %3$s.',
        ),
      ),

      '%s edited contributor(s), added %d: %s; removed %d: %s.' =>
        '%s edited contributors, added: %3$s; removed: %5$s',

      '%s added %d contributor(s): %s.' => array(
        array(
          '%s added a contributor: %3$s.',
          '%s added contributors: %3$s.',
        ),
      ),

      '%s removed %d contributor(s): %s.' => array(
        array(
          '%s removed a contributor: %3$s.',
          '%s removed contributors: %3$s.',
        ),
      ),

      '%s edited reviewer(s), added %d: %s; removed %d: %s.' =>
        '%s edited reviewers, added: %3$s; removed: %5$s',

      '%s added %d reviewer(s): %s.' => array(
        array(
          '%s added a reviewer: %3$s.',
          '%s added reviewers: %3$s.',
        ),
      ),

      '%s removed %d reviewer(s): %s.' => array(
        array(
          '%s removed a reviewer: %3$s.',
          '%s removed reviewers: %3$s.',
        ),
      ),

      '%s edited object(s), added %d: %s; removed %d: %s.' =>
        '%s edited objects, added: %3$s; removed: %5$s',

      '%s added %d object(s): %s.' => array(
        array(
          '%s added a object: %3$s.',
          '%s added objects: %3$s.',
        ),
      ),

      '%s removed %d object(s): %s.' => array(
        array(
          '%s removed a object: %3$s.',
          '%s removed objects: %3$s.',
        ),
      ),

      '%d other(s)' => array(
        '1 other',
        '%d others',
      ),

      '%s edited subscriber(s), added %d: %s; removed %d: %s.' =>
        '%s edited subscribers, added: %3$s; removed: %5$s',

      '%s added %d subscriber(s): %s.' => array(
        array(
          '%s added a subscriber: %3$s.',
          '%s added subscribers: %3$s.',
        ),
      ),

      '%s removed %d subscriber(s): %s.' => array(
        array(
          '%s removed a subscriber: %3$s.',
          '%s removed subscribers: %3$s.',
        ),
      ),

      '%s edited unsubscriber(s), added %d: %s; removed %d: %s.' =>
        '%s edited unsubscribers, added: %3$s; removed: %5$s',

      '%s added %d unsubscriber(s): %s.' => array(
        array(
          '%s added a unsubscriber: %3$s.',
          '%s added unsubscribers: %3$s.',
        ),
      ),

      '%s removed %d unsubscriber(s): %s.' => array(
        array(
          '%s removed a unsubscriber: %3$s.',
          '%s removed unsubscribers: %3$s.',
        ),
      ),

      '%s edited participant(s), added %d: %s; removed %d: %s.' =>
        '%s edited participants, added: %3$s; removed: %5$s',

      '%s added %d participant(s): %s.' => array(
        array(
          '%s added a participant: %3$s.',
          '%s added participants: %3$s.',
        ),
      ),

      '%s removed %d participant(s): %s.' => array(
        array(
          '%s removed a participant: %3$s.',
          '%s removed participants: %3$s.',
        ),
      ),

      '%s edited image(s), added %d: %s; removed %d: %s.' =>
        '%s edited images, added: %3$s; removed: %5$s',

      '%s added %d image(s): %s.' => array(
        array(
          '%s added an image: %3$s.',
          '%s added images: %3$s.',
        ),
      ),

      '%s removed %d image(s): %s.' => array(
        array(
          '%s removed an image: %3$s.',
          '%s removed images: %3$s.',
        ),
      ),

      '%d people(s)' => array(
        array(
          '%d person',
          '%d people',
        ),
      ),

      '%s Line(s)' => array(
        '%s Line',
        '%s Lines',
      ),

      "Indexing %d object(s) of type %s." => array(
        "Indexing %d object of type %s.",
        "Indexing %d object of type %s.",
      ),

      'Run these %d command(s):' => array(
        'Run this command:',
        'Run these commands:',
      ),

      'Install these %d PHP extension(s):' => array(
        'Install this PHP extension:',
        'Install these PHP extensions:',
      ),

      'The current Phabricator configuration has these %d value(s):' => array(
        'The current Phabricator configuration has this value:',
        'The current Phabricator configuration has these values:',
      ),

      'To update these %d value(s), run these command(s) from the command line:'
      => array(
        'To update this value, run this command from the command line:',
        'To update these values, run these commands from the command line:',
      ),

      'You can update these %d value(s) here:' => array(
        'You can update this value here:',
        'You can update these values here:',
      ),

      'The current PHP configuration has these %d value(s):' => array(
        'The current PHP configuration has this value:',
        'The current PHP configuration has these values:',
      ),

      'To update these %d value(s), edit your PHP configuration file.' => array(
        'To update this %d value, edit your PHP configuration file.',
        'To update these %d values, edit your PHP configuration file.',
      ),

      'To update these %d value(s), edit your PHP configuration file, located '.
      'here:' => array(
        'To update this value, edit your PHP configuration file, located '.
        'here:',
        'To update these values, edit your PHP configuration file, located '.
        'here:',
      ),

      'PHP also loaded these configuration file(s):' => array(
        'PHP also loaded this configuration file:',
        'PHP also loaded these configuration files:',
      ),

      'You have %d unresolved setup issue(s)...' => array(
        'You have an unresolved setup issue...',
        'You have %d unresolved setup issues...',
      ),

      '%s added %d inline comment(s).' => array(
        array(
          '%s added an inline comment.',
          '%s added inline comments.',
        ),
      ),

      '%d comment(s)' => array('%d comment', '%d comments'),
      '%d rejection(s)' => array('%d rejection', '%d rejections'),
      '%d update(s)' => array('%d update', '%d updates'),

      'This configuration value is defined in these %d '.
      'configuration source(s): %s.' => array(
        'This configuration value is defined in this '.
        'configuration source: %2$s.',
        'This configuration value is defined in these %d '.
        'configuration sources: %s.',
      ),

      '%d Open Pull Request(s)' => array(
        '%d Open Pull Request',
        '%d Open Pull Requests',
      ),

      'Stale (%s day(s))' => array(
        'Stale (%s day)',
        'Stale (%s days)',
      ),

      'Old (%s day(s))' => array(
        'Old (%s day)',
        'Old (%s days)',
      ),

      '%s Commit(s)' => array(
        '%s Commit',
        '%s Commits',
      ),

      '%s added %d project(s): %s' => array(
        array(
          '%s added a project: %3$s',
          '%s added projects: %3$s',
        ),
      ),

      '%s removed %d project(s): %s' => array(
        array(
          '%s removed a project: %3$s',
          '%s removed projects: %3$s',
        ),
      ),

      '%s changed project(s), added %d: %s; removed %d: %s' =>
        '%s changed projects, added: %3$s; removed: %5$s',

      '%s attached %d file(s): %s' => array(
        array(
          '%s attached a file: %3$s',
          '%s attached files: %3$s',
        ),
      ),

      '%s detached %d file(s): %s' => array(
        array(
          '%s detached a file: %3$s',
          '%s detached files: %3$s',
        ),
      ),

      '%s changed file(s), attached %d: %s; detached %d: %s' =>
        '%s changed files, attached: %3$s; detached: %5$s',


      '%s added %d dependencie(s): %s.' => array(
        array(
          '%s added a dependency: %3$s',
          '%s added dependencies: %3$s',
        ),
      ),

      '%s added %d dependent task(s): %s.' => array(
        array(
          '%s added a dependent task: %3$s',
          '%s added dependent tasks: %3$s',
        ),
      ),

      '%s removed %d dependencie(s): %s.' => array(
        array(
          '%s removed a dependency: %3$s.',
          '%s removed dependencies: %3$s.',
        ),
      ),

      '%s removed %d dependent task(s): %s.' => array(
        array(
          '%s removed a dependent task: %3$s.',
          '%s removed dependent tasks: %3$s.',
        ),
      ),

      '%s added %d revision(s): %s.' => array(
        array(
          '%s added a revision: %3$s.',
          '%s added revisions: %3$s.',
        ),
      ),

      '%s removed %d revision(s): %s.' => array(
        array(
          '%s removed a revision: %3$s.',
          '%s removed revisions: %3$s.',
        ),
      ),

      '%s added %d commit(s): %s.' => array(
        array(
          '%s added a commit: %3$s.',
          '%s added commits: %3$s.',
        ),
      ),

      '%s removed %d commit(s): %s.' => array(
        array(
          '%s removed a commit: %3$s.',
          '%s removed commits: %3$s.',
        ),
      ),

      '%s edited commit(s), added %d: %s; removed %d: %s.' =>
        '%s edited commits, added %3$s; removed %5$s.',

      '%s changed project member(s), added %d: %s; removed %d: %s' =>
        '%s changed project members, added %3$s; removed %5$s',

      '%s added %d project member(s): %s' => array(
        array(
          '%s added a member: %3$s',
          '%s added members: %3$s',
        ),
      ),

      '%s removed %d project member(s): %s' => array(
        array(
          '%s removed a member: %3$s',
          '%s removed members: %3$s',
        ),
      ),

      '%d User(s) Need Approval' => array(
        '%d User Needs Approval',
        '%d Users Need Approval',
      ),

      'Warning: there are %d signature(s) already for this document. '.
      'Updating the title or text will invalidate these signatures and users '.
      'will need to sign again. Proceed carefully.' => array(
        'Warning: there is %d signature already for this document. '.
        'Updating the title or text will invalidate this signature and the '.
        'user will need to sign again. Proceed carefully.',
        'Warning: there are %d signatures already for this document. '.
        'Updating the title or text will invalidate these signatures and '.
        'users will need to sign again. Proceed carefully.',
      ),

      '%s older changes(s) are hidden.' => array(
        '%d older change is hidden.',
        '%d older changes are hidden.',
      ),

      '%s, %d line(s)' => array(
        '%s, %d line',
        '%s, %d lines',
      ),

      '%s pushed %d commit(s) to %s.' => array(
        array(
          array(
            '%s pushed a commit to %3$s.',
            '%s pushed %d commits to %s.',
          ),
        ),
      ),

      '%s commit(s)' => array(
        '1 commit',
        '%s commits',
      ),

    );
  }

  }
