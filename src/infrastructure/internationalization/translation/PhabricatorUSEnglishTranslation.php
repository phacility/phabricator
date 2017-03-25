<?php

final class PhabricatorUSEnglishTranslation
  extends PhutilTranslation {

  public function getLocaleCode() {
    return 'en_US';
  }

  protected function getTranslations() {
    return array(
      'No daemon(s) with id(s) "%s" exist!' => array(
        'No daemon with id %s exists!',
        'No daemons with ids %s exist!',
      ),
      'These %d configuration value(s) are related:' => array(
        'This configuration value is related:',
        'These configuration values are related:',
      ),
      '%s Task(s)' => array('Task', 'Tasks'),

      '%s ERROR(S)' => array('ERROR', 'ERRORS'),
      '%d Error(s)' => array('%d Error', '%d Errors'),
      '%d Warning(s)' => array('%d Warning', '%d Warnings'),
      '%d Auto-Fix(es)' => array('%d Auto-Fix', '%d Auto-Fixes'),
      '%d Advice(s)' => array('%d Advice', '%d Pieces of Advice'),
      '%d Detail(s)' => array('%d Detail', '%d Details'),

      '(%d line(s))' => array('(%d line)', '(%d lines)'),

      '%d line(s)' => array('%d line', '%d lines'),
      '%d path(s)' => array('%d path', '%d paths'),
      '%d diff(s)' => array('%d diff', '%d diffs'),

      '%s Answer(s)' => array('%s Answer', '%s Answers'),
      'Show %d Comment(s)' => array('Show %d Comment', 'Show %d Comments'),

      '%s DIFF LINK(S)' => array('DIFF LINK', 'DIFF LINKS'),
      'You successfully created %d diff(s).' => array(
        'You successfully created %d diff.',
        'You successfully created %d diffs.',
      ),
      'Diff creation failed; see body for %s error(s).' => array(
        'Diff creation failed; see body for error.',
        'Diff creation failed; see body for errors.',
      ),

      'There are %d raw fact(s) in storage.' => array(
        'There is %d raw fact in storage.',
        'There are %d raw facts in storage.',
      ),

      'There are %d aggregate fact(s) in storage.' => array(
        'There is %d aggregate fact in storage.',
        'There are %d aggregate facts in storage.',
      ),

      '%s Commit(s) Awaiting Audit' => array(
        '%s Commit Awaiting Audit',
        '%s Commits Awaiting Audit',
      ),

      '%s Problem Commit(s)' => array(
        '%s Problem Commit',
        '%s Problem Commits',
      ),

      '%s Review(s) Blocking Others' => array(
        '%s Review Blocking Others',
        '%s Reviews Blocking Others',
      ),

      '%s Review(s) Need Attention' => array(
        '%s Review Needs Attention',
        '%s Reviews Need Attention',
      ),

      '%s Review(s) Waiting on Others' => array(
        '%s Review Waiting on Others',
        '%s Reviews Waiting on Others',
      ),

      '%s Active Review(s)' => array(
        '%s Active Review',
        '%s Active Reviews',
      ),

      '%s Flagged Object(s)' => array(
        '%s Flagged Object',
        '%s Flagged Objects',
      ),

      '%s Object(s) Tracked' => array(
        '%s Object Tracked',
        '%s Objects Tracked',
      ),

      '%s Assigned Task(s)' => array(
        '%s Assigned Task',
        '%s Assigned Tasks',
      ),

      'Show %d Lint Message(s)' => array(
        'Show %d Lint Message',
        'Show %d Lint Messages',
      ),
      'Hide %d Lint Message(s)' => array(
        'Hide %d Lint Message',
        'Hide %d Lint Messages',
      ),

      'This is a binary file. It is %s byte(s) in length.' => array(
        'This is a binary file. It is %s byte in length.',
        'This is a binary file. It is %s bytes in length.',
      ),

      '%s Action(s) Have No Effect' => array(
        'Action Has No Effect',
        'Actions Have No Effect',
      ),

      '%s Action(s) With No Effect' => array(
        'Action With No Effect',
        'Actions With No Effect',
      ),

      'Some of your %s action(s) have no effect:' => array(
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

      'The %s action(s) you are taking have no effect:' => array(
        'The action you are taking has no effect:',
        'The actions you are taking have no effect:',
      ),

      '%s edited member(s), added %d: %s; removed %d: %s.' =>
        '%s edited members, added: %3$s; removed: %5$s.',

      '%s added %s member(s): %s.' => array(
        array(
          '%s added a member: %3$s.',
          '%s added members: %3$s.',
        ),
      ),

      '%s removed %s member(s): %s.' => array(
        array(
          '%s removed a member: %3$s.',
          '%s removed members: %3$s.',
        ),
      ),

      '%s edited project(s), added %s: %s; removed %s: %s.' =>
        '%s edited projects, added: %3$s; removed: %5$s.',

      '%s added %s project(s): %s.' => array(
        array(
          '%s added a project: %3$s.',
          '%s added projects: %3$s.',
        ),
      ),

      '%s removed %s project(s): %s.' => array(
        array(
          '%s removed a project: %3$s.',
          '%s removed projects: %3$s.',
        ),
      ),

      '%s merged %s task(s): %s.' => array(
        array(
          '%s merged a task: %3$s.',
          '%s merged tasks: %3$s.',
        ),
      ),

      '%s merged %s task(s) %s into %s.' => array(
        array(
          '%s merged %3$s into %4$s.',
          '%s merged tasks %3$s into %4$s.',
        ),
      ),

      '%s added %s voting user(s): %s.' => array(
        array(
          '%s added a voting user: %3$s.',
          '%s added voting users: %3$s.',
        ),
      ),

      '%s removed %s voting user(s): %s.' => array(
        array(
          '%s removed a voting user: %3$s.',
          '%s removed voting users: %3$s.',
        ),
      ),

      '%s added %s subtask(s): %s.' => array(
        array(
          '%s added a subtask: %3$s.',
          '%s added subtasks: %3$s.',
        ),
      ),

      '%s added %s parent task(s): %s.' => array(
        array(
          '%s added a parent task: %3$s.',
          '%s added parent tasks: %3$s.',
        ),
      ),

      '%s removed %s subtask(s): %s.' => array(
        array(
          '%s removed a subtask: %3$s.',
          '%s removed subtasks: %3$s.',
        ),
      ),

      '%s removed %s parent task(s): %s.' => array(
        array(
          '%s removed a parent task: %3$s.',
          '%s removed parent tasks: %3$s.',
        ),
      ),

      '%s added %s subtask(s) for %s: %s.' => array(
        array(
          '%s added a subtask for %3$s: %4$s.',
          '%s added subtasks for %3$s: %4$s.',
        ),
      ),

      '%s added %s parent task(s) for %s: %s.' => array(
        array(
          '%s added a parent task for %3$s: %4$s.',
          '%s added parent tasks for %3$s: %4$s.',
        ),
      ),

      '%s removed %s subtask(s) for %s: %s.' => array(
        array(
          '%s removed a subtask for %3$s: %4$s.',
          '%s removed subtasks for %3$s: %4$s.',
        ),
      ),

      '%s removed %s parent task(s) for %s: %s.' => array(
        array(
          '%s removed a parent task for %3$s: %4$s.',
          '%s removed parent tasks for %3$s: %4$s.',
        ),
      ),

      '%s edited subtask(s), added %s: %s; removed %s: %s.' =>
        '%s edited subtasks, added: %3$s; removed: %5$s.',

      '%s edited subtask(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited subtasks for %s, added: %4$s; removed: %6$s.',

      '%s edited parent task(s), added %s: %s; removed %s: %s.' =>
        '%s edited parent tasks, added: %3$s; removed: %5$s.',

      '%s edited parent task(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited parent tasks for %s, added: %4$s; removed: %6$s.',

      '%s edited answer(s), added %s: %s; removed %d: %s.' =>
        '%s edited answers, added: %3$s; removed: %5$s.',

      '%s added %s answer(s): %s.' => array(
        array(
          '%s added an answer: %3$s.',
          '%s added answers: %3$s.',
        ),
      ),

      '%s removed %s answer(s): %s.' => array(
        array(
          '%s removed a answer: %3$s.',
          '%s removed answers: %3$s.',
        ),
      ),

     '%s edited question(s), added %s: %s; removed %s: %s.' =>
        '%s edited questions, added: %3$s; removed: %5$s.',

      '%s added %s question(s): %s.' => array(
        array(
          '%s added a question: %3$s.',
          '%s added questions: %3$s.',
        ),
      ),

      '%s removed %s question(s): %s.' => array(
        array(
          '%s removed a question: %3$s.',
          '%s removed questions: %3$s.',
        ),
      ),

      '%s edited mock(s), added %s: %s; removed %s: %s.' =>
        '%s edited mocks, added: %3$s; removed: %5$s.',

      '%s added %s mock(s): %s.' => array(
        array(
          '%s added a mock: %3$s.',
          '%s added mocks: %3$s.',
        ),
      ),

      '%s removed %s mock(s): %s.' => array(
        array(
          '%s removed a mock: %3$s.',
          '%s removed mocks: %3$s.',
        ),
      ),

      '%s added %s task(s): %s.' => array(
        array(
          '%s added a task: %3$s.',
          '%s added tasks: %3$s.',
        ),
      ),

      '%s removed %s task(s): %s.' => array(
        array(
          '%s removed a task: %3$s.',
          '%s removed tasks: %3$s.',
        ),
      ),

      '%s edited file(s), added %s: %s; removed %s: %s.' =>
        '%s edited files, added: %3$s; removed: %5$s.',

      '%s added %s file(s): %s.' => array(
        array(
          '%s added a file: %3$s.',
          '%s added files: %3$s.',
        ),
      ),

      '%s removed %s file(s): %s.' => array(
        array(
          '%s removed a file: %3$s.',
          '%s removed files: %3$s.',
        ),
      ),

      '%s edited contributor(s), added %s: %s; removed %s: %s.' =>
        '%s edited contributors, added: %3$s; removed: %5$s.',

      '%s added %s contributor(s): %s.' => array(
        array(
          '%s added a contributor: %3$s.',
          '%s added contributors: %3$s.',
        ),
      ),

      '%s removed %s contributor(s): %s.' => array(
        array(
          '%s removed a contributor: %3$s.',
          '%s removed contributors: %3$s.',
        ),
      ),

      '%s edited %s reviewer(s), added %s: %s; removed %s: %s.' =>
        '%s edited reviewers, added: %4$s; removed: %6$s.',

      '%s edited %s reviewer(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited reviewers for %3$s, added: %5$s; removed: %7$s.',

      '%s added %s reviewer(s): %s.' => array(
        array(
          '%s added a reviewer: %3$s.',
          '%s added reviewers: %3$s.',
        ),
      ),

      '%s added %s reviewer(s) for %s: %s.' => array(
        array(
          '%s added a reviewer for %3$s: %4$s.',
          '%s added reviewers for %3$s: %4$s.',
        ),
      ),

      '%s removed %s reviewer(s): %s.' => array(
        array(
          '%s removed a reviewer: %3$s.',
          '%s removed reviewers: %3$s.',
        ),
      ),

      '%s removed %s reviewer(s) for %s: %s.' => array(
        array(
          '%s removed a reviewer for %3$s: %4$s.',
          '%s removed reviewers for %3$s: %4$s.',
        ),
      ),

      '%d other(s)' => array(
        '1 other',
        '%d others',
      ),

      '%s edited subscriber(s), added %d: %s; removed %d: %s.' =>
        '%s edited subscribers, added: %3$s; removed: %5$s.',

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

      '%s edited watcher(s), added %s: %s; removed %d: %s.' =>
        '%s edited watchers, added: %3$s; removed: %5$s.',

      '%s added %s watcher(s): %s.' => array(
        array(
          '%s added a watcher: %3$s.',
          '%s added watchers: %3$s.',
        ),
      ),

      '%s removed %s watcher(s): %s.' => array(
        array(
          '%s removed a watcher: %3$s.',
          '%s removed watchers: %3$s.',
        ),
      ),

      '%s edited participant(s), added %d: %s; removed %d: %s.' =>
        '%s edited participants, added: %3$s; removed: %5$s.',

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

      '%s Line(s)' => array(
        '%s Line',
        '%s Lines',
      ),

      'Indexing %d object(s) of type %s.' => array(
        'Indexing %d object of type %s.',
        'Indexing %d object of type %s.',
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

      'The current MySQL configuration has these %d value(s):' => array(
        'The current MySQL configuration has this value:',
        'The current MySQL configuration has these values:',
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

      'PHP also loaded these %s configuration file(s):' => array(
        'PHP also loaded this configuration file:',
        'PHP also loaded these configuration files:',
      ),

      '%s added %d inline comment(s).' => array(
        array(
          '%s added an inline comment.',
          '%s added inline comments.',
        ),
      ),

      '%s comment(s)' => array('%s comment', '%s comments'),
      '%s rejection(s)' => array('%s rejection', '%s rejections'),
      '%s update(s)' => array('%s update', '%s updates'),

      'This configuration value is defined in these %d '.
      'configuration source(s): %s.' => array(
        'This configuration value is defined in this '.
        'configuration source: %2$s.',
        'This configuration value is defined in these %d '.
        'configuration sources: %s.',
      ),

      '%s Open Pull Request(s)' => array(
        '%s Open Pull Request',
        '%s Open Pull Requests',
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

      '%s attached %d file(s): %s.' => array(
        array(
          '%s attached a file: %3$s.',
          '%s attached files: %3$s.',
        ),
      ),

      '%s detached %d file(s): %s.' => array(
        array(
          '%s detached a file: %3$s.',
          '%s detached files: %3$s.',
        ),
      ),

      '%s changed file(s), attached %d: %s; detached %d: %s.' =>
        '%s changed files, attached: %3$s; detached: %5$s.',


      '%s added %s dependencie(s): %s.' => array(
        array(
          '%s added a dependency: %3$s.',
          '%s added dependencies: %3$s.',
        ),
      ),

      '%s added %s dependencie(s) for %s: %s.' => array(
        array(
          '%s added a dependency for %3$s: %4$s.',
          '%s added dependencies for %3$s: %4$s.',
        ),
      ),

      '%s removed %s dependencie(s): %s.' => array(
        array(
          '%s removed a dependency: %3$s.',
          '%s removed dependencies: %3$s.',
        ),
      ),

      '%s removed %s dependencie(s) for %s: %s.' => array(
        array(
          '%s removed a dependency for %3$s: %4$s.',
          '%s removed dependencies for %3$s: %4$s.',
        ),
      ),

      '%s edited dependencie(s), added %s: %s; removed %s: %s.' => array(
        '%s edited dependencies, added: %3$s; removed: %5$s.',
      ),

      '%s edited dependencie(s) for %s, added %s: %s; removed %s: %s.' => array(
        '%s edited dependencies for %s, added: %3$s; removed: %5$s.',
      ),

      '%s added %s dependent revision(s): %s.' => array(
        array(
          '%s added a dependent revision: %3$s.',
          '%s added dependent revisions: %3$s.',
        ),
      ),

      '%s added %s dependent revision(s) for %s: %s.' => array(
        array(
          '%s added a dependent revision for %3$s: %4$s.',
          '%s added dependent revisions for %3$s: %4$s.',
        ),
      ),

      '%s removed %s dependent revision(s): %s.' => array(
        array(
          '%s removed a dependent revision: %3$s.',
          '%s removed dependent revisions: %3$s.',
        ),
      ),

      '%s removed %s dependent revision(s) for %s: %s.' => array(
        array(
          '%s removed a dependent revision for %3$s: %4$s.',
          '%s removed dependent revisions for %3$s: %4$s.',
        ),
      ),

      '%s added %s commit(s): %s.' => array(
        array(
          '%s added a commit: %3$s.',
          '%s added commits: %3$s.',
        ),
      ),

      '%s removed %s commit(s): %s.' => array(
        array(
          '%s removed a commit: %3$s.',
          '%s removed commits: %3$s.',
        ),
      ),

      '%s edited commit(s), added %s: %s; removed %s: %s.' =>
        '%s edited commits, added %3$s; removed %5$s.',

      '%s added %s reverted commit(s): %s.' => array(
        array(
          '%s added a reverted commit: %3$s.',
          '%s added reverted commits: %3$s.',
        ),
      ),

      '%s removed %s reverted commit(s): %s.' => array(
        array(
          '%s removed a reverted commit: %3$s.',
          '%s removed reverted commits: %3$s.',
        ),
      ),

      '%s edited reverted commit(s), added %s: %s; removed %s: %s.' =>
        '%s edited reverted commits, added %3$s; removed %5$s.',

      '%s added %s reverted commit(s) for %s: %s.' => array(
        array(
          '%s added a reverted commit for %3$s: %4$s.',
          '%s added reverted commits for %3$s: %4$s.',
        ),
      ),

      '%s removed %s reverted commit(s) for %s: %s.' => array(
        array(
          '%s removed a reverted commit for %3$s: %4$s.',
          '%s removed reverted commits for %3$s: %4$s.',
        ),
      ),

      '%s edited reverted commit(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited reverted commits for %2$s, added %4$s; removed %6$s.',

      '%s added %s reverting commit(s): %s.' => array(
        array(
          '%s added a reverting commit: %3$s.',
          '%s added reverting commits: %3$s.',
        ),
      ),

      '%s removed %s reverting commit(s): %s.' => array(
        array(
          '%s removed a reverting commit: %3$s.',
          '%s removed reverting commits: %3$s.',
        ),
      ),

      '%s edited reverting commit(s), added %s: %s; removed %s: %s.' =>
        '%s edited reverting commits, added %3$s; removed %5$s.',

      '%s added %s reverting commit(s) for %s: %s.' => array(
        array(
          '%s added a reverting commit for %3$s: %4$s.',
          '%s added reverting commitsi for %3$s: %4$s.',
        ),
      ),

      '%s removed %s reverting commit(s) for %s: %s.' => array(
        array(
          '%s removed a reverting commit for %3$s: %4$s.',
          '%s removed reverting commits for %3$s: %4$s.',
        ),
      ),

      '%s edited reverting commit(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited reverting commits for %s, added %4$s; removed %6$s.',

      '%s changed project member(s), added %d: %s; removed %d: %s.' =>
        '%s changed project members, added %3$s; removed %5$s.',

      '%s added %d project member(s): %s.' => array(
        array(
          '%s added a member: %3$s.',
          '%s added members: %3$s.',
        ),
      ),

      '%s removed %d project member(s): %s.' => array(
        array(
          '%s removed a member: %3$s.',
          '%s removed members: %3$s.',
        ),
      ),

      '%s project hashtag(s) are already used by other projects: %s.' => array(
        'Project hashtag "%2$s" is already used by another project.',
        'Some project hashtags are already used by other projects: %2$s.',
      ),

      '%s changed project hashtag(s), added %d: %s; removed %d: %s.' =>
        '%s changed project hashtags, added %3$s; removed %5$s.',

      'Hashtags must contain at least one letter or number. %s '.
      'project hashtag(s) are invalid: %s.' => array(
        'Hashtags must contain at least one letter or number. The '.
        'hashtag "%2$s" is not valid.',
        'Hashtags must contain at least one letter or number. These '.
        'hashtags are invalid: %2$s.',
      ),

      '%s added %d project hashtag(s): %s.' => array(
        array(
          '%s added a hashtag: %3$s.',
          '%s added hashtags: %3$s.',
        ),
      ),

      '%s removed %d project hashtag(s): %s.' => array(
        array(
          '%s removed a hashtag: %3$s.',
          '%s removed hashtags: %3$s.',
        ),
      ),

      '%s changed %s hashtag(s), added %d: %s; removed %d: %s.' =>
        '%s changed hashtags for %s, added %4$s; removed %6$s.',

      '%s added %d %s hashtag(s): %s.' => array(
        array(
          '%s added a hashtag to %3$s: %4$s.',
          '%s added hashtags to %3$s: %4$s.',
        ),
      ),

      '%s removed %d %s hashtag(s): %s.' => array(
        array(
          '%s removed a hashtag from %3$s: %4$s.',
          '%s removed hashtags from %3$s: %4$s.',
        ),
      ),

      '%d User(s) Need Approval' => array(
        '%d User Needs Approval',
        '%d Users Need Approval',
      ),

      '%s, %s line(s)' => array(
        array(
          '%s, %s line',
          '%s, %s lines',
        ),
      ),

      '%s pushed %d commit(s) to %s.' => array(
        array(
          '%s pushed a commit to %3$s.',
          '%s pushed %d commits to %s.',
        ),
      ),

      '%s commit(s)' => array(
        '1 commit',
        '%s commits',
      ),

      '%s removed %s JIRA issue(s): %s.' => array(
        array(
          '%s removed a JIRA issue: %3$s.',
          '%s removed JIRA issues: %3$s.',
        ),
      ),

      '%s added %s JIRA issue(s): %s.' => array(
        array(
          '%s added a JIRA issue: %3$s.',
          '%s added JIRA issues: %3$s.',
        ),
      ),

      '%s added %s required legal document(s): %s.' => array(
        array(
          '%s added a required legal document: %3$s.',
          '%s added required legal documents: %3$s.',
        ),
      ),

      '%s updated JIRA issue(s): added %s %s; removed %d %s.' =>
        '%s updated JIRA issues: added %3$s; removed %5$s.',

      '%s edited %s task(s), added %s: %s; removed %s: %s.' =>
        '%s edited tasks, added %4$s; removed %6$s.',

      '%s added %s task(s) to %s: %s.' => array(
        array(
          '%s added a task to %3$s: %4$s.',
          '%s added tasks to %3$s: %4$s.',
        ),
      ),

      '%s removed %s task(s) from %s: %s.' => array(
        array(
          '%s removed a task from %3$s: %4$s.',
          '%s removed tasks from %3$s: %4$s.',
        ),
      ),

      '%s edited %s task(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited tasks for %3$s, added: %5$s; removed %7$s.',

      '%s edited %s commit(s), added %s: %s; removed %s: %s.' =>
        '%s edited commits, added %4$s; removed %6$s.',

      '%s added %s commit(s) to %s: %s.' => array(
        array(
          '%s added a commit to %3$s: %4$s.',
          '%s added commits to %3$s: %4$s.',
        ),
      ),

      '%s removed %s commit(s) from %s: %s.' => array(
        array(
          '%s removed a commit from %3$s: %4$s.',
          '%s removed commits from %3$s: %4$s.',
        ),
      ),

      '%s edited %s commit(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited commits for %3$s, added: %5$s; removed %7$s.',

      '%s added %s revision(s): %s.' => array(
        array(
          '%s added a revision: %3$s.',
          '%s added revisions: %3$s.',
        ),
      ),

      '%s removed %s revision(s): %s.' => array(
        array(
          '%s removed a revision: %3$s.',
          '%s removed revisions: %3$s.',
        ),
      ),

      '%s edited %s revision(s), added %s: %s; removed %s: %s.' =>
        '%s edited revisions, added %4$s; removed %6$s.',

      '%s added %s revision(s) to %s: %s.' => array(
        array(
          '%s added a revision to %3$s: %4$s.',
          '%s added revisions to %3$s: %4$s.',
        ),
      ),

      '%s removed %s revision(s) from %s: %s.' => array(
        array(
          '%s removed a revision from %3$s: %4$s.',
          '%s removed revisions from %3$s: %4$s.',
        ),
      ),

      '%s edited %s revision(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited revisions for %3$s, added: %5$s; removed %7$s.',

      '%s edited %s project(s), added %s: %s; removed %s: %s.' =>
        '%s edited projects, added %4$s; removed %6$s.',

      '%s added %s project(s) to %s: %s.' => array(
        array(
          '%s added a project to %3$s: %4$s.',
          '%s added projects to %3$s: %4$s.',
        ),
      ),

      '%s removed %s project(s) from %s: %s.' => array(
        array(
          '%s removed a project from %3$s: %4$s.',
          '%s removed projects from %3$s: %4$s.',
        ),
      ),

      '%s edited %s project(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited projects for %3$s, added: %5$s; removed %7$s.',

      '%s added %s panel(s): %s.' => array(
        array(
          '%s added a panel: %3$s.',
          '%s added panels: %3$s.',
        ),
      ),

      '%s removed %s panel(s): %s.' => array(
        array(
          '%s removed a panel: %3$s.',
          '%s removed panels: %3$s.',
        ),
      ),

      '%s edited %s panel(s), added %s: %s; removed %s: %s.' =>
        '%s edited panels, added %4$s; removed %6$s.',

      '%s added %s dashboard(s): %s.' => array(
        array(
          '%s added a dashboard: %3$s.',
          '%s added dashboards: %3$s.',
        ),
      ),

      '%s removed %s dashboard(s): %s.' => array(
        array(
          '%s removed a dashboard: %3$s.',
          '%s removed dashboards: %3$s.',
        ),
      ),

      '%s edited %s dashboard(s), added %s: %s; removed %s: %s.' =>
        '%s edited dashboards, added %4$s; removed %6$s.',

      '%s added %s edge(s): %s.' => array(
        array(
          '%s added an edge: %3$s.',
          '%s added edges: %3$s.',
        ),
      ),

      '%s added %s edge(s) to %s: %s.' => array(
        array(
          '%s added an edge to %3$s: %4$s.',
          '%s added edges to %3$s: %4$s.',
        ),
      ),

      '%s removed %s edge(s): %s.' => array(
        array(
          '%s removed an edge: %3$s.',
          '%s removed edges: %3$s.',
        ),
      ),

      '%s removed %s edge(s) from %s: %s.' => array(
        array(
          '%s removed an edge from %3$s: %4$s.',
          '%s removed edges from %3$s: %4$s.',
        ),
      ),

      '%s edited edge(s), added %s: %s; removed %s: %s.' =>
        '%s edited edges, added: %3$s; removed: %5$s.',

      '%s edited %s edge(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited edges for %3$s, added: %5$s; removed %7$s.',

      '%s added %s member(s) for %s: %s.' => array(
        array(
          '%s added a member for %3$s: %4$s.',
          '%s added members for %3$s: %4$s.',
        ),
      ),

      '%s removed %s member(s) for %s: %s.' => array(
        array(
          '%s removed a member for %3$s: %4$s.',
          '%s removed members for %3$s: %4$s.',
        ),
      ),

      '%s edited %s member(s) for %s, added %s: %s; removed %s: %s.' =>
        '%s edited members for %3$s, added: %5$s; removed %7$s.',

      '%d related link(s):' => array(
        'Related link:',
        'Related links:',
      ),

      'You have %d unpaid invoice(s).' => array(
        'You have an unpaid invoice.',
        'You have unpaid invoices.',
      ),

      'The configurations differ in the following %s way(s):' => array(
        'The configurations differ:',
        'The configurations differ in these ways:',
      ),

      'Phabricator is configured with an email domain whitelist (in %s), so '.
      'only users with a verified email address at one of these %s '.
      'allowed domain(s) will be able to register an account: %s' => array(
        array(
          'Phabricator is configured with an email domain whitelist (in %s), '.
          'so only users with a verified email address at %3$s will be '.
          'allowed to register an account.',
          'Phabricator is configured with an email domain whitelist (in %s), '.
          'so only users with a verified email address at one of these '.
          'allowed domains will be able to register an account: %3$s',
        ),
      ),

      'Show First %d Line(s)' => array(
        'Show First Line',
        'Show First %d Lines',
      ),

      "\xE2\x96\xB2 Show %d Line(s)" => array(
        "\xE2\x96\xB2 Show Line",
        "\xE2\x96\xB2 Show %d Lines",
      ),

      'Show All %d Line(s)' => array(
        'Show Line',
        'Show All %d Lines',
      ),

      "\xE2\x96\xBC Show %d Line(s)" => array(
        "\xE2\x96\xBC Show Line",
        "\xE2\x96\xBC Show %d Lines",
      ),

      'Show Last %d Line(s)' => array(
        'Show Last Line',
        'Show Last %d Lines',
      ),

      '%s marked %s inline comment(s) as done and %s inline comment(s) as '.
      'not done.' => array(
        array(
          array(
            '%s marked an inline comment as done and an inline comment '.
            'as not done.',
            '%s marked an inline comment as done and %3$s inline comments '.
            'as not done.',
          ),
          array(
            '%s marked %s inline comments as done and an inline comment '.
            'as not done.',
            '%s marked %s inline comments as done and %s inline comments '.
            'as done.',
          ),
        ),
      ),

      '%s marked %s inline comment(s) as done.' => array(
        array(
          '%s marked an inline comment as done.',
          '%s marked %s inline comments as done.',
        ),
      ),

      '%s marked %s inline comment(s) as not done.' => array(
        array(
          '%s marked an inline comment as not done.',
          '%s marked %s inline comments as not done.',
        ),
      ),

      'These %s object(s) will be destroyed forever:' => array(
        'This object will be destroyed forever:',
        'These objects will be destroyed forever:',
      ),

      'Are you absolutely certain you want to destroy these %s '.
      'object(s)?' => array(
        'Are you absolutely certain you want to destroy this object?',
        'Are you absolutely certain you want to destroy these objects?',
      ),

      '%s added %s owner(s): %s.' => array(
        array(
          '%s added an owner: %3$s.',
          '%s added owners: %3$s.',
        ),
      ),

      '%s removed %s owner(s): %s.' => array(
        array(
          '%s removed an owner: %3$s.',
          '%s removed owners: %3$s.',
        ),
      ),

      '%s changed %s package owner(s), added %s: %s; removed %s: %s.' => array(
        '%s changed package owners, added: %4$s; removed: %6$s.',
      ),

      'Found %s book(s).' => array(
        'Found %s book.',
        'Found %s books.',
      ),
      'Found %s file(s)...' => array(
        'Found %s file...',
        'Found %s files...',
      ),
      'Found %s file(s) in project.' => array(
        'Found %s file in project.',
        'Found %s files in project.',
      ),
      'Found %s unatomized, uncached file(s).' => array(
        'Found %s unatomized, uncached file.',
        'Found %s unatomized, uncached files.',
      ),
      'Found %s file(s) to atomize.' => array(
        'Found %s file to atomize.',
        'Found %s files to atomize.',
      ),
      'Atomizing %s file(s).' => array(
        'Atomizing %s file.',
        'Atomizing %s files.',
      ),
      'Creating %s document(s).' => array(
        'Creating %s document.',
        'Creating %s documents.',
      ),
      'Deleting %s document(s).' => array(
        'Deleting %s document.',
        'Deleting %s documents.',
      ),
      'Found %s obsolete atom(s) in graph.' => array(
        'Found %s obsolete atom in graph.',
        'Found %s obsolete atoms in graph.',
      ),
      'Found %s new atom(s) in graph.' => array(
        'Found %s new atom in graph.',
        'Found %s new atoms in graph.',
      ),
      'This call takes %s parameter(s), but only %s are documented.' => array(
        array(
          'This call takes %s parameter, but only %s is documented.',
          'This call takes %s parameter, but only %s are documented.',
        ),
        array(
          'This call takes %s parameters, but only %s is documented.',
          'This call takes %s parameters, but only %s are documented.',
        ),
      ),

      '%s Passed Test(s)' => '%s Passed',
      '%s Failed Test(s)' => '%s Failed',
      '%s Skipped Test(s)' => '%s Skipped',
      '%s Broken Test(s)' => '%s Broken',
      '%s Unsound Test(s)' => '%s Unsound',
      '%s Other Test(s)' => '%s Other',

      '%s Bulk Task(s)' => array(
        '%s Task',
        '%s Tasks',
      ),

      '%s added %s badge(s) for %s: %s.' => array(
        array(
          '%s added a badge for %s: %3$s.',
          '%s added badges for %s: %3$s.',
        ),
      ),
      '%s added %s badge(s): %s.' => array(
        array(
          '%s added a badge: %3$s.',
          '%s added badges: %3$s.',
        ),
      ),
      '%s awarded %s recipient(s) for %s: %s.' => array(
        array(
          '%s awarded %3$s to %4$s.',
          '%s awarded %3$s to multiple recipients: %4$s.',
        ),
      ),
      '%s awarded %s recipients(s): %s.' => array(
        array(
          '%s awarded a recipient: %3$s.',
          '%s awarded multiple recipients: %3$s.',
        ),
      ),
      '%s edited badge(s) for %s, added %s: %s; revoked %s: %s.' => array(
        array(
          '%s edited badges for %s, added %s: %s; revoked %s: %s.',
          '%s edited badges for %s, added %s: %s; revoked %s: %s.',
        ),
      ),
      '%s edited badge(s), added %s: %s; revoked %s: %s.' => array(
        array(
          '%s edited badges, added %s: %s; revoked %s: %s.',
          '%s edited badges, added %s: %s; revoked %s: %s.',
        ),
      ),
      '%s edited recipient(s) for %s, awarded %s: %s; revoked %s: %s.' => array(
        array(
          '%s edited recipients for %s, awarded %s: %s; revoked %s: %s.',
          '%s edited recipients for %s, awarded %s: %s; revoked %s: %s.',
        ),
      ),
      '%s edited recipient(s), awarded %s: %s; revoked %s: %s.' => array(
        array(
          '%s edited recipients, awarded %s: %s; revoked %s: %s.',
          '%s edited recipients, awarded %s: %s; revoked %s: %s.',
        ),
      ),
      '%s revoked %s badge(s) for %s: %s.' => array(
        array(
          '%s revoked a badge for %3$s: %4$s.',
          '%s revoked multiple badges for %3$s: %4$s.',
        ),
      ),
      '%s revoked %s badge(s): %s.' => array(
        array(
          '%s revoked a badge: %3$s.',
          '%s revoked multiple badges: %3$s.',
        ),
      ),
      '%s revoked %s recipient(s) for %s: %s.' => array(
        array(
          '%s revoked %3$s from %4$s.',
          '%s revoked multiple recipients for %3$s: %4$s.',
        ),
      ),

      '%s revoked %s recipients(s): %s.' => array(
        array(
          '%s revoked a recipient: %3$s.',
          '%s revoked multiple recipients: %3$s.',
        ),
      ),

      '%s automatically subscribed target(s) were not affected: %s.' => array(
        'An automatically subscribed target was not affected: %2$s.',
        'Automatically subscribed targets were not affected: %2$s.',
      ),

      'Declined to resubscribe %s target(s) because they previously '.
      'unsubscribed: %s.' => array(
        'Delined to resubscribe a target because they previously '.
        'unsubscribed: %2$s.',
        'Declined to resubscribe targets because they previously '.
        'unsubscribed: %2$s.',
      ),

      '%s target(s) are not subscribed: %s.' => array(
        'A target is not subscribed: %2$s.',
        'Targets are not subscribed: %2$s.',
      ),

      '%s target(s) are already subscribed: %s.' => array(
        'A target is already subscribed: %2$s.',
        'Targets are already subscribed: %2$s.',
      ),

      'Added %s subscriber(s): %s.' => array(
        'Added a subscriber: %2$s.',
        'Added subscribers: %2$s.',
      ),

      'Removed %s subscriber(s): %s.' => array(
        'Removed a subscriber: %2$s.',
        'Removed subscribers: %2$s.',
      ),

      'Queued email to be delivered to %s target(s): %s.' => array(
        'Queued email to be delivered to target: %2$s.',
        'Queued email to be delivered to targets: %2$s.',
      ),

      'Queued email to be delivered to %s target(s), ignoring their '.
      'notification preferences: %s.' => array(
        'Queued email to be delivered to target, ignoring notification '.
        'preferences: %2$s.',
        'Queued email to be delivered to targets, ignoring notification '.
        'preferences: %2$s.',
      ),

      '%s project(s) are not associated: %s.' => array(
        'A project is not associated: %2$s.',
        'Projects are not associated: %2$s.',
      ),

      '%s project(s) are already associated: %s.' => array(
        'A project is already associated: %2$s.',
        'Projects are already associated: %2$s.',
      ),

      'Added %s project(s): %s.' => array(
        'Added a project: %2$s.',
        'Added projects: %2$s.',
      ),

      'Removed %s project(s): %s.' => array(
        'Removed a project: %2$s.',
        'Removed projects: %2$s.',
      ),

      'Added %s reviewer(s): %s.' => array(
        'Added a reviewer: %2$s.',
        'Added reviewers: %2$s.',
      ),

      'Added %s blocking reviewer(s): %s.' => array(
        'Added a blocking reviewer: %2$s.',
        'Added blocking reviewers: %2$s.',
      ),

      'Required %s signature(s): %s.' => array(
        'Required a signature: %2$s.',
        'Required signatures: %2$s.',
      ),

      'Started %s build(s): %s.' => array(
        'Started a build: %2$s.',
        'Started builds: %2$s.',
      ),

      'Added %s auditor(s): %s.' => array(
        'Added an auditor: %2$s.',
        'Added auditors: %2$s.',
      ),

      '%s target(s) do not have permission to see this object: %s.' => array(
        'A target does not have permission to see this object: %2$s.',
        'Targets do not have permission to see this object: %2$s.',
      ),

      'This action has no effect on %s target(s): %s.' => array(
        'This action has no effect on a target: %2$s.',
        'This action has no effect on targets: %2$s.',
      ),

      'Mail sent in the last %s day(s).' => array(
        'Mail sent in the last day.',
        'Mail sent in the last %s days.',
      ),

      '%s Day(s)' => array(
        '%s Day',
        '%s Days',
      ),
      '%s Day(s) Ago' => array(
        '%s Day Ago',
        '%s Days Ago',
      ),

      'Setting retention policy for "%s" to %s day(s).' => array(
        array(
          'Setting retention policy for "%s" to one day.',
          'Setting retention policy for "%s" to %s days.',
        ),
      ),

      'Waiting %s second(s) for lease to activate.' => array(
        'Waiting a second for lease to activate.',
        'Waiting %s seconds for lease to activate.',
      ),

      '%s changed %s automation blueprint(s), added %s: %s; removed %s: %s.' =>
        '%s changed automation blueprints, added: %4$s; removed: %6$s.',

      '%s added %s automation blueprint(s): %s.' => array(
        array(
          '%s added an automation blueprint: %3$s.',
          '%s added automation blueprints: %3$s.',
        ),
      ),

      '%s removed %s automation blueprint(s): %s.' => array(
        array(
          '%s removed an automation blueprint: %3$s.',
          '%s removed automation blueprints: %3$s.',
        ),
      ),

      'WARNING: There are %s unapproved authorization(s)!' => array(
        'WARNING: There is an unapproved authorization!',
        'WARNING: There are unapproved authorizations!',
      ),

      'Found %s Open Resource(s)' => array(
        'Found %s Open Resource',
        'Found %s Open Resources',
      ),

      '%s Open Resource(s) Remain' => array(
        '%s Open Resource Remain',
        '%s Open Resources Remain',
      ),

      'Found %s Blueprint(s)' => array(
        'Found %s Blueprint',
        'Found %s Blueprints',
      ),

      '%s Blueprint(s) Can Allocate' => array(
        '%s Blueprint Can Allocate',
        '%s Blueprints Can Allocate',
      ),

      '%s Blueprint(s) Enabled' => array(
        '%s Blueprint Enabled',
        '%s Blueprints Enabled',
      ),

      '%s Event(s)' => array(
        '%s Event',
        '%s Events',
      ),

      '%s Unit(s)' => array(
        '%s Unit',
        '%s Units',
      ),

      'QUEUEING TASKS (%s Commit(s)):' => array(
        'QUEUEING TASKS (%s Commit):',
        'QUEUEING TASKS (%s Commits):',
      ),

      'Found %s total commit(s); updating...' => array(
        'Found %s total commit; updating...',
        'Found %s total commits; updating...',
      ),

      'Not enough process slots to schedule the other %s '.
      'repository(s) for updates yet.' => array(
        'Not enough process slots to schedule the other '.'
        repository for update yet.',
        'Not enough process slots to schedule the other %s '.
        'repositories for updates yet.',
      ),


      '%s updated %s, added %d: %s.' =>
        '%s updated %s, added: %4$s.',

      '%s updated %s, removed %s: %s.' =>
        '%s updated %s, removed: %4$s.',

      '%s updated %s, added %s: %s; removed %s: %s.' =>
        '%s updated %s, added: %4$s; removed: %6$s.',

      '%s updated %s for %s, added %d: %s.' =>
        '%s updated %s for %s, added: %5$s.',

      '%s updated %s for %s, removed %s: %s.' =>
        '%s updated %s for %s, removed: %5$s.',

      '%s updated %s for %s, added %s: %s; removed %s: %s.' =>
        '%s updated %s for %s, added: %5$s; removed; %7$s.',

      'Permanently destroyed %s object(s).' => array(
        'Permanently destroyed %s object.',
        'Permanently destroyed %s objects.',
      ),

      '%s added %s watcher(s) for %s: %s.' => array(
        array(
          '%s added a watcher for %3$s: %4$s.',
          '%s added watchers for %3$s: %4$s.',
        ),
      ),

      '%s removed %s watcher(s) for %s: %s.' => array(
        array(
          '%s removed a watcher for %3$s: %4$s.',
          '%s removed watchers for %3$s: %4$s.',
        ),
      ),

      '%s awarded this badge to %s recipient(s): %s.' => array(
        array(
          '%s awarded this badge to recipient: %3$s.',
          '%s awarded this badge to recipients: %3$s.',
        ),
      ),

      '%s revoked this badge from %s recipient(s): %s.' => array(
        array(
          '%s revoked this badge from recipient: %3$s.',
          '%s revoked this badge from recipients: %3$s.',
        ),
      ),

      '%s awarded %s to %s recipient(s): %s.' => array(
        array(
          array(
            '%s awarded %s to recipient: %4$s.',
            '%s awarded %s to recipients: %4$s.',
          ),
        ),
      ),

      '%s revoked %s from %s recipient(s): %s.' => array(
        array(
          array(
            '%s revoked %s from recipient: %4$s.',
            '%s revoked %s from recipients: %4$s.',
          ),
        ),
      ),

      '%s invited %s attendee(s): %s.' =>
        '%s invited: %3$s.',

      '%s uninvited %s attendee(s): %s.' =>
        '%s uninvited: %3$s.',

      '%s invited %s attendee(s): %s; uninvinted %s attendee(s): %s.' =>
        '%s invited: %3$s; uninvited: %5$s.',

      '%s invited %s attendee(s) to %s: %s.' =>
        '%s added invites for %3$s: %4$s.',

      '%s uninvited %s attendee(s) to %s: %s.' =>
        '%s removed invites for %3$s: %4$s.',

      '%s updated the invite list for %s, invited %s: %s; uninvinted %s: %s.' =>
        '%s updated the invite list for %s, invited: %4$s; uninvited: %6$s.',

      'Restart %s build(s)?' => array(
        'Restart %s build?',
        'Restart %s builds?',
      ),

      '%s is starting in %s minute(s), at %s.' => array(
        array(
          '%s is starting in one minute, at %3$s.',
          '%s is starting in %s minutes, at %s.',
        ),
      ),

      '%s added %s auditor(s): %s.' => array(
        array(
          '%s added an auditor: %3$s.',
          '%s added auditors: %3$s.',
        ),
      ),

      '%s removed %s auditor(s): %s.' => array(
        array(
          '%s removed an auditor: %3$s.',
          '%s removed auditors: %3$s.',
        ),
      ),

      '%s edited %s auditor(s), removed %s: %s; added %s: %s.' => array(
        array(
          '%s edited auditors, removed: %4$s; added: %6$s.',
        ),
      ),

      '%s accepted this revision as %s reviewer(s): %s.' =>
        '%s accepted this revision as: %3$s.',
    );
  }

}
