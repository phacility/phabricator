alter table {$NAMESPACE}_maniphest.maniphest_task add projectPHIDs longblob not null;
update {$NAMESPACE}_maniphest.maniphest_task set projectPHIDs = '[]';
