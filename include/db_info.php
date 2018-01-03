<?php
// db: records_srb2rec

// read only:
// user: records_readonly
// pass: (Maybe not)
function open_database_readonly() {
	mysql::connect('localhost', 'records_readonly', '(Maybe not)');
	mysql_set_charset('utf8');
	mysql::selectdb('records_srb2rec');
}

// all privileges:
// user: records_internal
// pass: (Sorry, but you're not getting this password for free)
function open_database_readwrite() {
	mysql::connect('localhost', 'records_internal', '(Sorry, but you\'re not getting this password for free)');
	mysql_set_charset('utf8');
	mysql::selectdb('records_srb2rec');
}

// mb database (for login):
// db: srb2mb_vbulletin
// user: srb2mb_records
// pass: (Same for this one)
function open_database_srb2mb() {
	mysql::connect('localhost', 'srb2mb_records', '(Same for this one)');
	//srb2mb database is not in utf8
	mysql::selectdb('srb2mb_vbulletin');
}