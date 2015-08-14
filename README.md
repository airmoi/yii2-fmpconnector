Yii2 FileMaker Connector
========================
FileMaker ODBC connector and PHP-API integration

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist airmoi/yii2-fmconnector "*"
```

or add

```
"airmoi/yii2-fmconnector": "*"
```

to the require section of your `composer.json` file.


Usage
-----
0. ODBC connection

Once plugin installed using composer, and ODBC driver configured on the server,
Create/Edit your db config file using this lines
```php
return [
    'class' => 'airmoi\yii2fmconnector\db\Connection',
    'dsn' => 'fmp:<odbc_connection_name>',
    'username' => '<odbc_username>',
    'password' => '<odbc_username>',
    'charset' => 'utf8',
    'pdoClass' => 'airmoi\yii2fmconnector\db\PDO',
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 86400,
    //'enableQueryCache' => true,
    //'queryCacheDuration' => 1000,
    'schemaMap' => ['fmp' => [
            'class' => 'airmoi\yii2fmconnector\db\Schema',
            /* 
             * Customize this option to ignore specific fields (like global/utils fields) which you don't want to get access
             * Ignore theses fields improve query performences
             */
            'ignoreFields' => [
                'FieldType' => ['global%'],
                'FieldClass' => ['Summary'],
                'FieldName' => ['zkk_%',
                    'zgi_%',
                    'zg_%',
                    'zz_%',
                    'zzz_%',
                    'zlg_%',
                    'z_foundCount_cU',
                    'z_listOf_eval_cU',
                ]
            ],
            /* 
             * Regexp pattern used to detect if a field is a primary key
             * this pattern while be used against fields names
             */
            'primaryKeyPattern' => '/^zkp(_)?/',
            /* 
             * pattern used to detect if a field is a foreign key
             * this pattern while be used against fields names
             * Second match of the pattern must return the foreign key trigram (XXX)
             */
            'foreignKeyPattern' => '/^(zkf|zkp)_([^_]*).*/', //pattern used to detect if a field is a foreign key
        ]
    ]
];
```

0. PHP-API

add and customize this lines into the components section of your config file

```
...
    'components" => [
            ...
            'fmphelper' => [
                        'class' => 'airmoi\yii2fmconnector\api\FmpHelper',
                        'host' => "localhost",
                        'db' => 'your_dn_name',
                        'username' => '',
                        'password' => '',
                        'resultLayout' => 'PHP_scriptResult', //Layout used to return performScriptResult
                        'resultField' => 'PHP_scriptResult', //Field used in "resultLayout" to store script results
                        'valueListLayout' => 'PHP_valueLists', //Layout used to retrieve generic valueLists
                    ],
            ...
    ],
...
```

Acces FileMaker API using fmhelper component
```php
<?php 
Yii::$app->fmhelper
?>
```

0. Customize gii

Add these lines to gii module config to enhance model & CRUD generators
```php
'generators' => [
                    'model' => [
                        'class' => 'yii\gii\generators\model\Generator',
                        'templates' => [
                            'FileMaker' => '@app/vendor/airmoi/yii2-fmconnector/gii/templates/',
                        ]
                    ],
                     'crud' => [ // generator name
                        'class' => 'airmoi\yii2fmconnector\gii\crud\Generator', // generator class
                    ]
                ],
        ];
```

