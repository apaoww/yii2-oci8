Yii2 Connect Oracle via OCI8
============================

This library is based on  [yajra/pdo-via-oci8](https://github.com/yajra/pdo-via-oci8) Version 3 that support PHP 8.2.

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

### OR Install From Archive
You can also install from archive. Add aliase on config file to point alias to the folder
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

Once the extension is installed, simply modify your application configuration on main-local.php as follows :

```
return [	
	'components' => [
		....
		'db' => [
                    'class' => 'apaoww\oci8\Oci8DbConnection',
                    'dsn' => 'oci:dbname=(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=127.0.0.1)(PORT=1521))(CONNECT_DATA=(SID=xe)));charset=AL32UTF8;',
                    'username' => 'yourdatabaseschemaname',
                    'password' => 'databasepassword',
		    'enableSchemaCache' => true, //increase performance when retrieved table meta data
            	    'schemaCacheDuration' => 3600,
            	    'schemaCache' => 'cache',
		    'on afterOpen' => function($event) {

                /* A session configuration example */
                $q = <<<SQL
begin
  dbms_session.set_role('NAME_OF_YOUR_ROLE_IN_ORACLE');
  EXCEPTION  -- exception handlers begin
   WHEN OTHERS THEN  -- handles all other errors
      ROLLBACK;
end;
SQL;
                $event->sender->createCommand($q)->execute();
            },
                    // To convert column name to lower case
                    'schemaMap' => ['oci' => 'apaoww\oci8\ESchemaOci',],
    			'attributes' => [
	//                PDO::ATTR_STRINGIFY_FETCHES => true,
			        PDO::ATTR_CASE => PDO::CASE_LOWER,
			    ],
	                ],
	],
];
```

