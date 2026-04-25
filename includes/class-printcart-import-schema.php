<?php
if (!defined('ABSPATH')) {
    exit;
}

class SPBWC_Printcart_Import_Schema {
    public function init() {
        add_action('spbwc_create_tables', array($this, 'create_tables'));
    }

    public function create_tables() {
        global $wpdb;
        $collate = '';
        if ($wpdb->has_cap('collation')) {
            $collate = $wpdb->get_charset_collate();
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $tables = "
CREATE TABLE {$wpdb->prefix}nbdesigner_templates ( 
 id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
 product_id BIGINT(20) UNSIGNED NOT NULL,
 variation_id BIGINT(20) NULL, 
 folder varchar(255) NOT NULL,
 user_id BIGINT(20) NULL, 
 created_date DATETIME NOT NULL default '0000-00-00 00:00:00',
 publish TINYINT(1) NOT NULL default 1,
 private TINYINT(1) NOT NULL default 0,
 priority  TINYINT(1) NOT NULL default 0,
 hit BIGINT(20) NULL, 
 sales INT(10) NOT NULL default 0,
 vote INT(10) NOT NULL default 0,
 name varchar(255) NULL,
 type varchar(255) NULL,
 resource varchar(255) NULL,
 tags varchar(255) NULL,
 colors varchar(255) NULL,
 thumbnail INT(10) NULL,
 PRIMARY KEY  (id) 
) $collate;
CREATE TABLE {$wpdb->prefix}nbdesigner_mydesigns (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT(20) NOT NULL, 
  folder varchar(255) NOT NULL,
  product_id BIGINT(20) UNSIGNED NOT NULL,
  variation_id BIGINT(20) NULL,   
  price varchar(255) NOT NULL default '0',
  selling TINYINT(1) NOT NULL default 0,
  vote INT(10) NOT NULL default 0,
  publish TINYINT(1) NOT NULL default 1,
  created_date DATETIME NOT NULL default '0000-00-00 00:00:00',
  hit INT(10) NOT NULL default 0,
  sales INT(10) NOT NULL default 0,
  PRIMARY KEY  (id)
) $collate;
CREATE TABLE {$wpdb->prefix}nbdesigner_user_designs (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT(20) NOT NULL, 
  folder varchar(255) NOT NULL,
  created_date DATETIME NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
) $collate;
CREATE TABLE {$wpdb->prefix}nbdesigner_options ( 
 id bigint(20) unsigned NOT NULL auto_increment,
 title text NOT NULL,
 priority  TINYINT(1) NOT NULL default 0, 
 published  TINYINT(1) NOT NULL default 1, 
 product_ids text NULL, 
 product_cats text NULL,  
 date_from TINYTEXT NULL,  
 date_to TINYTEXT NULL,  
 apply_for TINYTEXT NULL,  
 enabled_roles text NULL,  
 disabled_roles text NULL,  
 created datetime NOT NULL default '0000-00-00 00:00:00',
 modified datetime NOT NULL default '0000-00-00 00:00:00', 
 created_by BIGINT(20) NULL, 
 modified_by BIGINT(20) NULL,  
 fields longtext,
 builder text NULL,
 PRIMARY KEY  (id)
) $collate;
        ";
        dbDelta($tables);
    }
}

function spbwc_printcart_import_schema_init() {
    $schema = new SPBWC_Printcart_Import_Schema();
    $schema->init();
}
add_action('init', 'spbwc_printcart_import_schema_init');
