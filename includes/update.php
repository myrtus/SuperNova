<?php
/*
 update.php

 Automated DB upgrade system

 @package supernova
 @version 24

 24 - copyright (c) 2009-2011 Gorlum for http://supernova.ws
   [+] Converted pre v18 entries to use later implemented functions
 v18-v23 - copyright (c) 2009-2010 Gorlum for http://supernova.ws
   [!] DB code updates
 17 - copyright (c) 2009-2010 Gorlum for http://supernova.ws
   [~] PCG1 compliant

 v01-v16 copyright (c) 2009-2010 Gorlum for http://supernova.ws
   [!] DB code updates
*/

if(!defined('INIT'))
{
  include_once('init.php');
}

$config->db_loadItem('db_version');
if($config->db_version == DB_VERSION)
{
//  return;
}

if($config->db_version > DB_VERSION)
{
  die('Internal error! Auotupdater detects DB version greater then can be handled!<br>Possible you have out-of-date SuperNova version<br>Pleas upgrade your server from <a href="http://github.com/supernova-ws/SuperNova">GIT repository</a>.');
}

$upd_log = '';

upd_log_message('Update starting. Loading table info...');
$query = doquery('SHOW TABLES;');
while($row = mysql_fetch_row($query))
{
  $tableName = str_replace($config->db_prefix, '', $row[0]);

  $q1 = doquery("SHOW COLUMNS FROM {$row[0]};");
  while($r1 = mysql_fetch_assoc($q1))
  {
    $update_tables[$tableName][$r1['Field']] = $r1;
  }

  $q1 = doquery("SHOW INDEX FROM {$row[0]};");
  while($r1 = mysql_fetch_assoc($q1))
  {
    $update_indexes[$tableName][$r1['Key_name']] .= "{$r1['Column_name']},";
  }
}
upd_log_message('Table info loaded. Now looking DB for upgrades...');

$new_version = $config->db_version;
switch(intval($config->db_version))
{
  case 0:
    upd_log_version_update();
    upd_alter_table('planets', array(
      "ADD `parent_planet` bigint(11) unsigned DEFAULT '0'",
      "ADD KEY `i_parent_planet` (`parent_planet`)"
    ), !$update_tables['planets']['parent_planet']);
    upd_do_query(
      "UPDATE `{{planets}}` AS lu
        LEFT JOIN `{{planets}}` AS pl
          ON pl.galaxy=lu.galaxy AND pl.system=lu.system AND pl.planet=lu.planet AND pl.planet_type=1
      SET lu.parent_planet=pl.id WHERE lu.planet_type=3;"
    );
  doquery('COMMIT;');
  $new_version = 1;

  case 1:
    upd_log_version_update();
    if(!$update_tables['counter'])
    {
      mysql_query(
        "CREATE TABLE `{$config->db_prefix}counter` (
          `id` bigint(11) NOT NULL AUTO_INCREMENT,
          `time` int(11) NOT NULL DEFAULT '0',
          `page` varchar(255) CHARACTER SET utf8 DEFAULT '0',
          `user_id` bigint(11) DEFAULT '0',
          `ip` varchar(15) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `i_user_id` (`user_id`),
          KEY `i_ip` (`ip`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
      );
    };
  doquery('COMMIT;');
  $new_version = 2;

  case 2:
    upd_log_version_update();
    if($update_tables['lunas'])
    {
      mysql_query("DROP TABLE IF EXISTS {$config->db_prefix}lunas;");
    }
  doquery('COMMIT;');
  $new_version = 3;

  case 3:
    upd_log_version_update();
    upd_alter_table('counter', "ADD `url` varchar(255) CHARACTER SET utf8 DEFAULT ''", !$update_tables['counter']['url']);
  doquery('COMMIT;');
  $new_version = 4;

  case 4:
    upd_log_version_update();
    upd_alter_table('planets', "ADD `debris_metal` bigint(11) unsigned DEFAULT '0'", !$update_tables['planets']['debris_metal']);
    upd_alter_table('planets', "ADD `debris_crystal` bigint(11) unsigned DEFAULT '0'", !$update_tables['planets']['debris_crystal']);

    if($update_tables['galaxy'])
    {
      upd_do_query(
        'UPDATE `{{planets}}`
          LEFT JOIN `{{galaxy}}` ON {{galaxy}}.id_planet = {{planets}}.id
        SET
          {{planets}}.debris_metal = {{galaxy}}.metal,
          {{planets}}.debris_crystal = {{galaxy}}.crystal
        WHERE {{galaxy}}.metal>0 OR {{galaxy}}.crystal>0;'
      );
    }
  doquery('COMMIT;');
  $new_version = 5;

  case 5:
    upd_log_version_update();
    mysql_query("DROP TABLE IF EXISTS `{$config->db_prefix}galaxy`;");
  doquery('COMMIT;');
  $new_version = 6;

  case 6:
    upd_log_version_update();
    upd_do_query("DELETE FROM {{config}} WHERE `config_name` IN ('BannerURL', 'banner_source_post', 'BannerOverviewFrame',
      'close_reason', 'dbVersion', 'ForumUserBarFrame', 'OverviewBanner', 'OverviewClickBanner', 'OverviewExternChat',
      'OverviewExternChatCmd', 'OverviewNewsText', 'UserbarURL', 'userbar_source');");
  doquery('COMMIT;');
  $new_version = 7;

  case 7:
    upd_log_version_update();
    upd_alter_table('fleets', array(
      "ADD KEY `fleet_mess` (`fleet_mess`)",
      "ADD KEY `fleet_group` (`fleet_group`)"
    ), !$update_indexes['fleets']['fleet_mess']);
  doquery('COMMIT;');
  $new_version = 8;

  case 8:
    upd_log_version_update();

    upd_alter_table('referrals', "ADD `dark_matter` bigint(11) NOT NULL DEFAULT '0' COMMENT 'How much player have aquired Dark Matter'", !$update_tables['referrals']['dark_matter']);
    upd_alter_table('referrals', "ADD KEY `id_partner` (`id_partner`)", !$update_indexes['referrals']['id_partner']);

    upd_check_key('rpg_bonus_divisor', 10);
    upd_check_key('rpg_officer', 3);
  doquery('COMMIT;');
  $new_version = 9;

  case 9:
    upd_log_version_update();

    $dm_change_legit = true;

    upd_do_query(
      "UPDATE {{referrals}} AS r
        LEFT JOIN {{users}} AS u
          ON u.id = r.id
      SET r.dark_matter = u.lvl_minier + u.lvl_raid;"
    );
    upd_add_more_time();

    upd_do_query(
      "UPDATE {{users}} AS u
        RIGHT JOIN {{referrals}} AS r
          ON r.id_partner = u.id AND r.dark_matter >= {$config->rpg_bonus_divisor}
      SET u.rpg_points = u.rpg_points + FLOOR(r.dark_matter/{$config->rpg_bonus_divisor});"
    );

    $dm_change_legit = false;
  doquery('COMMIT;');
  $new_version = 10;

  case 10:
    upd_log_version_update();
    upd_check_key('game_news_overview', 3);
    upd_check_key('game_news_actual', 259200);
  doquery('COMMIT;');
  $new_version = 11;

  case 11:
    upd_log_version_update();

    upd_alter_table('users', array(
      "DROP COLUMN `aktywnosc`",
      "DROP COLUMN `time_aktyw`",
      "DROP COLUMN `kiler`",
      "DROP COLUMN `kod_aktywujacy`",
      "DROP COLUMN `ataker`",
      "DROP COLUMN `atakin`"
    ), $update_tables['users']['ataker']);

    upd_do_query("DELETE FROM {{config}} WHERE `config_name` IN ('OverviewNewsFrame');");
  doquery('COMMIT;');
  $new_version = 12;

  case 12:
    upd_log_version_update();
    upd_alter_table('planets', "ADD `supercargo` bigint(11) NOT NULL DEFAULT '0' COMMENT 'Supercargo ship count'", !$update_tables['planets']['supercargo']);
    if(!$update_tables['alliance_requests'])
    {
      mysql_query(
        "CREATE TABLE `{$config->db_prefix}alliance_requests` (
          `id_user` int(11) NOT NULL,
          `id_ally` int(11) NOT NULL DEFAULT '0',
          `request_text` text,
          `request_time` int(11) NOT NULL DEFAULT '0',
          `request_denied` tinyint(1) unsigned NOT NULL DEFAULT '0',
          PRIMARY KEY (`id_user`,`id_ally`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
      );
    };
  doquery('COMMIT;');
  $new_version = 13;

  case 13:
    upd_log_version_update();
    mysql_query("DROP TABLE IF EXISTS `{$config->db_prefix}update`;");
  $new_version = 14;

  case 14:
    upd_log_version_update();
    upd_check_key('rules_url', '/rules.php');
  doquery('COMMIT;');
  $new_version = 15;

  case 15:
    upd_log_version_update();
    upd_alter_table('users', "DROP COLUMN `current_luna`", $update_tables['users']['current_luna']);
    upd_alter_table('users', "ADD `options` TEXT COMMENT 'Packed user options'", !$update_tables['users']['options']);
  doquery('COMMIT;');
  $new_version = 16;

  case 16:
    upd_log_version_update();
    upd_check_key('game_speed', $config->game_speed/2500, $config->game_speed >= 2500);
    upd_check_key('fleet_speed', $config->fleet_speed/2500, $config->fleet_speed >= 2500);
    upd_check_key('player_max_colonies', $config->player_max_planets ? ($config->player_max_planets - 1) : 9);
    upd_do_query("DELETE FROM {{config}} WHERE `config_name` IN ('player_max_planets');");
    upd_alter_table('users', "ADD `news_lastread` int(11) NOT NULL DEFAULT '0' COMMENT 'News last read tag'", !$update_tables['users']['news_lastread']);
  doquery('COMMIT;');
  $new_version = 17;

  case 17:
    upd_log_version_update();
    upd_check_key('game_default_language', 'ru');
    upd_check_key('game_default_skin', 'skins/EpicBlue/');
    upd_check_key('game_default_template', 'OpenGame');
    upd_alter_table('announce', "ADD `detail_url` varchar(250) NOT NULL DEFAULT '' COMMENT 'Link to more details about update'", !$update_tables['announce']['detail_url']);
  doquery('COMMIT;');
  $new_version = 18;

  case 18:
    upd_log_version_update();

    upd_check_key('game_counter', 1);

    upd_check_key('int_format_date', 'd.m.Y');
    upd_check_key('int_format_time', 'H:i:s');
    upd_do_query("DELETE FROM {{config}} WHERE `config_name` IN ('game_date_withTime');");

    upd_alter_table('users', array(
      "MODIFY `user_lastip` VARCHAR(250) COMMENT 'User last IP'",
      "ADD `user_proxy` VARCHAR(250) NOT NULL DEFAULT '' COMMENT 'User proxy (if any)'"
    ), !$update_tables['users']['user_proxy']);

    upd_alter_table('counter', array(
      "MODIFY `ip` VARCHAR(250) COMMENT 'User last IP'",
      "ADD `proxy` VARCHAR(250) NOT NULL DEFAULT '' COMMENT 'User proxy (if any)'"
    ), !$update_tables['counter']['proxy']);
  doquery('COMMIT;');
  $new_version = 19;

  case 19:
    upd_log_version_update();
    upd_check_key('int_format_time', 'H:i:s', true);
    upd_check_key('int_banner_background', 'design/images/banner.png', true);
    upd_check_key('int_userbar_background', 'design/images/userbar.png', true);
    upd_do_query('UPDATE {{planets}} SET `metal_mine` = `metal_mine` - 1 WHERE `metal_mine` > 5;');
  doquery('COMMIT;');
  $new_version = 20;

  case 20:
    upd_log_version_update();
    upd_alter_table('statpoints', array(
      "ADD `res_rank` INT(11) DEFAULT 0 COMMENT 'Rank by resources'",
      "ADD `res_old_rank` INT(11) DEFAULT 0 COMMENT 'Old rank by resources'",
      "ADD `res_points` BIGINT(20) DEFAULT 0 COMMENT 'Resource stat points'",
      "ADD `res_count` BIGINT(20) DEFAULT 0 COMMENT 'Old rank by resources'"
    ), !$update_tables['statpoints']['res_rank']);
  doquery('COMMIT;');
  $new_version = 21;

  case 21:
    upd_log_version_update();
    upd_check_key('game_noob_points', 5000, true);
    upd_check_key('game_noob_factor', 5, true);

    upd_check_key('url_forum', $config->forum_url, !$config->url_forum);
    upd_check_key('url_rules', $config->rules_url, !$config->url_rules);
    upd_check_key('url_dark_matter', '/dark_matter_get.php', !$config->url_dark_matter);
    upd_do_query("DELETE FROM {{config}} WHERE `config_name` IN ('forum_url', 'rules_url');");

  doquery('COMMIT;');
  $new_version = 22;

  case 22:
    upd_log_version_update();
    upd_alter_table('planets', "ADD `governor` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Planet governor'", !$update_tables['planets']['governor']);
    upd_alter_table('planets', "ADD `governor_level` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Governor level'", !$update_tables['planets']['governor_level']);
    upd_alter_table('planets', "ADD `que` varchar(4096) NOT NULL DEFAULT '' COMMENT 'Planet que'", !$update_tables['planets']['que']);

    $planet_query = upd_do_query('SELECT * FROM {{planets}} WHERE `b_building` <> 0;');
    $const_que_structures = QUE_STRUCTURES;
    while($planet_data = mysql_fetch_assoc($planet_query))
    {
      $old_que = explode(';', $planet_data['b_building_id']);
      foreach($old_que as $old_que_item_string)
      {
        if(!$old_que_item_string)
        {
          continue;
        }

        $old_que_item = explode(',', $old_que_item_string);
        if($old_que_item[4] == 'build')
        {
          $old_que_item[4] = BUILD_CREATE;
        }
        else
        {
          $old_que_item[4] = BUILD_DESTROY;
        }

        $old_que_item[3] = $old_que_item[3] > $planet_data['last_update'] ? $old_que_item[3] - $planet_data['last_update'] : 1;
        $planet_data['que'] = "{$old_que_item[0]},1,{$old_que_item[3]},{$old_que_item[4]},{$const_que_structures};{$planet_data['que']}";
      }
      upd_do_query("UPDATE {{planets}} SET `que` = '{$planet_data['que']}', `b_building` = '0', `b_building_id` = '0' WHERE `id` = '{$planet_data['id']}' LIMIT 1;");
    }

    if(!$update_tables['mercenaries'])
    {
      mysql_query(
        "CREATE TABLE `{$config->db_prefix}mercenaries` (
          `id` bigint(11) NOT NULL AUTO_INCREMENT,
          `id_user` bigint(11) NOT NULL,
          `mercenary` UNSIGNED SMALLINT NOT NULL DEFAULT '0',
          `time_start` int(11) NOT NULL DEFAULT '0',
          `time_finish` int(11) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`),
          KEY `i_user_mercenary_time` (`id_user`, `mercenary`, `time_start`, `time_finish`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
      );
    };
  doquery('COMMIT;');
  $new_version = 23;

  case 23:
    upd_log_version_update();
    if(!$update_tables['confirmations'])
    {
      $result = mysql_query(
        "CREATE TABLE `{$config->db_prefix}confirmations` (
          `id` bigint(11) NOT NULL AUTO_INCREMENT,
          `id_user` bigint(11) NOT NULL DEFAULT 0,
          `type` SMALLINT NOT NULL DEFAULT 0,
          `code` NVARCHAR(16) NOT NULL DEFAULT '',
          `email` NVARCHAR(64) NOT NULL DEFAULT '',
          `create_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `i_code_email` (`code`, `email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
      );
    };
  doquery('COMMIT;');
  $new_version = 24;

  case 24:
    upd_log_version_update();

    if(!$update_tables['users']['vacation'])
    {
      upd_alter_table('users', "ADD `vacation` int(11) NOT NULL DEFAULT '0' COMMENT 'Time when user can leave vacation mode'", !$update_tables['users']['vacation']);
      upd_do_query('UPDATE {{users}} SET `vacation` = `urlaubs_until`;');
      upd_alter_table('users', 'DROP COLUMN `urlaubs_until`, DROP COLUMN `urlaubs_modus`, DROP COLUMN `urlaubs_modus_time`');
    }

    if(isset($config->urlaubs_modus_erz))
    {
      upd_check_key('user_vacation_disable', $config->urlaubs_modus_erz, !isset($config->user_vacation_disable));
      upd_do_query("DELETE FROM {{config}} WHERE `config_name` IN ('urlaubs_modus_erz');");
      unset($config->urlaubs_modus_erz);
    }

  doquery('COMMIT;');
  //$new_version = 25;

};
upd_log_message('Upgrade complete.');

if($new_version)
{
  $config->db_saveItem('db_version', $new_version);
  upd_log_message("<font color=green>DB version is now {$new_version}</font>");
}
else
{
  upd_log_message("DB version didn't changed from {$config->db_version}");
}

//if ( $user['authlevel'] >= 3 )
{
  print(str_replace("\r\n", '<br>', $upd_log));
}

function upd_do_query($query)
{
  global $config;

  upd_add_more_time();
  upd_log_message("Performing query '{$query}'");

  return doquery($query);
}

function upd_alter_table($table, $alters, $condition = true)
{
  global $config;

  if(!$condition)
  {
    return;
  }

  upd_add_more_time();
  upd_log_message("Altering table '{$table}' with alterations '{$alters}'");

  if(!is_array($alters))
  {
    $alters = array($alters);
  }

  $qry = "ALTER TABLE {$config->db_prefix}{$table}";
  foreach($alters as $alteration)
  {
    if($alteration)
    {
      $qry .= " {$alteration},";
    }
  }
  $qry = substr($qry, 0, -1) . ';';

  return mysql_query($qry);
}

function upd_check_key($key, $default_value, $condition = false)
{
  global $config;

  if($condition || !$config->db_loadItem($key))
  {
    upd_add_more_time();
    upd_log_message("Updating config key '{$key}' with value '{$default_value}'");
    $config->db_saveItem($key, $default_value);
  }
}

function upd_log_version_update()
{
  global $new_version;

  doquery('START TRANSACTION;');
  upd_add_more_time();
  upd_log_message("Detected outdated version {$new_version}. Upgrading...");
}

function upd_add_more_time()
{
  global $config, $time_now;

  $config->db_saveItem('var_db_update_end', $time_now + 60);
  set_time_limit(60);
}

function upd_log_message($message)
{
  global $upd_log, $debug;

  $upd_log .= "{$message}\r\n";
  $debug->warning($message, 'Database Update', 103);
}

?>
