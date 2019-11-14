#
# Table structure for table 'pages'
#
CREATE TABLE pages (
	tx_staticfilecache_cache tinyint(1) DEFAULT '1',
	tx_staticfilecache_cache_force tinyint(1) DEFAULT '0'
);

#
# Table structure for table 'tx_staticfilecache_queue'
#
CREATE TABLE tx_staticfilecache_queue (
	identifier varchar(255) DEFAULT '' NOT NULL,
	cache_url text NOT NULL,
	page_uid int(11) DEFAULT '0' NOT NULL,
	invalid_date int(11) DEFAULT '0' NOT NULL,
	call_date int(11) DEFAULT '0' NOT NULL,
	call_result tinytext NOT NULL,
	PRIMARY KEY (identifier)
);
