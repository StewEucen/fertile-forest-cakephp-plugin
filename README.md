# Fertile Forest Model for Plugin of CakePHP 3.x

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)

This plugin is an implementation of the Fertile Forest Model for CakePHP 3.x.

## What's Fertile Forest Model?

We know four models for storing hierarchical data in a database.

1. Adjacency List Model
2. Path Emuneration Model
3. Nested Sets Model (Nested Intervals Model)
4. Closure Table Model

Fertile Forest Model (= FF Model) is the fifth Model for storing hierarchical data in database. Stew Eucen who is Japanese database engineer discovered it. FF Model has some excellent features than each conventional model.

Many libraries of framework use "Nested Sets Model" for storing hierarchical data in RDB. However, we got the new model now. I think that Nested Sets Model will be replaced by FF Model in Future. My plan is that CakePHP 4.x contains ForestBehavior.

## More information

You can learn more about Fertile Forest Model at:

* [Fertile Forest Model (Official)](http://lab.kochlein.com/FertileForest)

## About Plugin

This is the plugin for CakePHP 3.x. Core files are only two as:

* [plugins/FertileForest/src/Model/Behavior/FertileForestBehavior.php](https://github.com/StewEucen/fertile-forest-cakephp-plugin/blob/master/src/Model/Behavior/FertileForestBehavior.php)
* [plugins/FertileForest/src/Model/Entity/FertileForestTrait.php](https://github.com/StewEucen/fertile-forest-cakephp-plugin/blob/master/src/Model/Entity/FertileForestTrait.php)

This is minimum component to use Fertile Forest Model for your projects.

## Environments

I confirmed this model operation by the environments:

* PHP 5.6.3
* CakePHP 3.1.0
* MySQL 5.6.23

## Demo

This plugin contains demo pages for using FertileForestBehavior. You can experience Fertile Forest Model through the demo. Please see:

* [Usage of Demo](https://github.com/StewEucen/fertile-forest-cakephp-plugin/blob/master/DEMO.md)

## How to contribute

* If you find a bug, or want to contribute an enhancement or a fix, please send a pull request according to GitHub rules.<br>
https://github.com/StewEucen/fertile-forest-cakephp-plugin

* Please post in your SNS:
```
We got the new model for storing hierarchical data in a database.
Stew Eucen did it!
```

Copyright © 2015 Stew Eucen, released under the MIT license
