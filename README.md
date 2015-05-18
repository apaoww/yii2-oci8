Yii2 Oracle Oci8 Driver 
=======================

Installation
------------

### Install With Composer

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require apaoww/yii2-oci8 "dev-master"
```

or add

```
"apaoww/yii2-oci8": "dev-master"
```

to the require section of your `composer.json` file.

### Install From Archive

```
return [
    ...
    'aliases' => [
        '@apaoww/oci8' => 'path/to/your/extracted',
        ...
    ]
];
```

Usage
-----

Once the extension is installed, simply modify your application configuration as follows :

```
return [	
	'components' => [
		....
		'db' => [
                    'class' => 'apaoww\oci8\Oci8DbConnection',
                    'dsn' => 'oci8:dbname=(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=127.0.0.1)(PORT=1521))(CONNECT_DATA=(SID=xe)));charset=AL32UTF8;', // Oracle
                    'username' => 'databaseusername',
                    'password' => 'databasepassword',
                    'attributes' => [
                        PDO::ATTR_STRINGIFY_FETCHES => true,
                    ]
                ],
	],
];
```
Custom User's Table Migration
---------------------------

You may want to create user's table using migration command. Instead of using yii default migrate (yii migrate), specify the custom migrationPath to point to custom user's table migration to avoid oracle error ('ORA-00907: missing right parenthesis). Note: you have to manually add user's table sequence  and primary key trigger using sql developer or toad.

```
yii migrate --migrationPath=@apaoww/oci8/migrations
```
