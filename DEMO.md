# Demo for Fertile Forest Behavior

This plugin contains demo pages for using FertileForestBehavior. You can experience Fertile Forest Model through the demo.

## Settings for Demo

Settings for this plugin is four steps as follows.

### (1) Create MySQL tables.
Table of single_woods and multiple_woods are for demo pages.
```
CREATE TABLE `single_woods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ff_depth` int(11) unsigned NOT NULL,
  `ff_queue` int(11) unsigned NOT NULL,
  `title` varchar(225) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ff_depth_index` (`ff_depth`,`ff_queue`),
  KEY `ff_queue_index` (`ff_queue`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
```
```
CREATE TABLE `multiple_woods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '1',
  `ff_depth` int(11) NOT NULL,
  `ff_queue` int(11) NOT NULL,
  `title` varchar(225) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ff_depth_index` (`user_id`,`ff_depth`,`ff_queue`),
  KEY `ff_queue_index` (`user_id`,`ff_queue`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
```
### (2) Add into composer.json of your project root as:
```
"require": {
    ...
    ...
    "stew-eucen/fertile-forest": "*"
},
```
### (3) Add into config/bootstrap.php as:
```
Plugin::load('FertileForest', ['bootstrap' => false, 'routes' => true]);
```
### (4) View by Browser.
When your project root is "ForestDemo" on localhost, URL is as follows. Please see plugins/FertileForest/config/routes.php.
```
http://localhost/ForestDemo/demo
```
