UPDATE phabricator_project.project SET status = IF(status = 5, 100, 0);
