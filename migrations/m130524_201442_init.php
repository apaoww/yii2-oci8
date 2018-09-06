<?php

use apaoww\oci8\Schema;
use yii\db\Migration;

class m130524_201442_init extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%USER}}', [
            'ID' => Schema::TYPE_PK,
            'USERNAME' => Schema::TYPE_STRING . ' NOT NULL',
            'AUTH_KEY' => Schema::TYPE_STRING . '(32) NOT NULL',
            'PASSWORD_HASH' => Schema::TYPE_STRING . ' NOT NULL',
            'PASSWORD_RESET_TOKEN' => Schema::TYPE_STRING,
            'EMAIL' => Schema::TYPE_STRING . ' NOT NULL',

            'STATUS' => Schema::TYPE_SMALLINT . ' NOT NULL',
            'CREATED_AT' => Schema::TYPE_INTEGER . ' NOT NULL',
            'UPDATED_AT' => Schema::TYPE_INTEGER . ' NOT NULL',
        ], $tableOptions);
    }

    public function down()
    {
        $this->dropTable('{{%USER}}');
    }
}
