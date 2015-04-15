Yii2 Oci8 Driver extend from Yii1 oci8 extension by yjeroen/oci8Pdo
===================================================================

Installation
------------

### Install With Composer

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require apaoww/yii2-oci8 "~1.0"
```

or add

```
"apaoww/yii2-oci8": "~1.0"
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
