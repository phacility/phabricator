alter table phabricator_maniphest.maniphest_task add projectPHIDs longblob not null;
update phabricator_maniphest.maniphest_task set projectPHIDs = '[]';
