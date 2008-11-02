### CAKEPHP DATABASE MIGRATIONS

Database Migrations for CakePHP 1.2 is a shell script supported by the CakePHP console, that lets you
manage your database schema without touching one little bit of SQL. It is based on the Ruby on Rails
implementation of Migrations, and uses Pear's MDB2 package, so supports all the databases that that
supports.

You could think of Migrations as a version control system for your database. It's power lends itself
perfectly to developing as part of a team, as each member can keep their own independent copy of
their application's database, and use Migrations to make changes to its schema. All other members
have to do then, is to run a simple two word shell command, and their database copy is up to date
with everyone else's.

The Migrations shell will generate a migration file for each DB change you want to make. This file
can include any number of DB changes.

The Migration files support YAML and PHP array's. So instead of having to write SQL queries, you
can write a short YAML structure that will do the same thing:

    create_table:
      users:
        name: string
        age: int
        is_active: bool
      
or the equivalent in a PHP array:

    array(
      'create_table' => array(
        'users' => array(
          'name' => array(
            'type' => 'string'
          )
          'age' => array(
            'type' => 'int'
          )
          'is_active' => array(
            'type' => 'bool'
          )
        )
      )
    )

The above YAML code will create a table called 'users'. This is equivalent to running the following
MySQL code:

    CREATE TABLE users (
      id int(11) NOT NULL auto_increment,
      name varchar(255) default NULL,
      age int(11) default NULL,
      is_active tinyint(1) default NULL,
      created datetime default NULL,
      modified datetime default NULL,
      PRIMARY KEY  (id)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1;

This package also includes an extremely easy to use Fixtures shell that works seamlessly with
Migrations. Fixtures also use YAML and are a great way to insert test and/or development data
into your database.



Please check out the examples directory within this package.

Updates, new releases and other resources can be found at [http://code.google.com/p/cakephp-migrations/](http://code.google.com/p/cakephp-migrations/)
For further assistance and additional resources, please check out my Blog at [http://developingwithstyle.com](http://developingwithstyle.com)