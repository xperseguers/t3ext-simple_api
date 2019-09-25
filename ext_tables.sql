#
# Table structure for table 'tx_simpleapi_cache_queue'
#
CREATE TABLE tx_simpleapi_cache_queue (
	uid int(11) NOT NULL auto_increment,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cache_tag varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (uid)
) ENGINE=InnoDB;
