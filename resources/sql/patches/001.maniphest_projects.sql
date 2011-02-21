alter table maniphest_task add projectPHIDs longblob not null;
update maniphest_task set projectPHIDs = '[]';