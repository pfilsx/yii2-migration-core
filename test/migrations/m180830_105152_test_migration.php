<?php

use yii\db\oci8\Migration;

/**
 * Class m180830_105152_test_migration
 */
class m180830_105152_test_migration extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('TEST_MIGRATION_CORE', [
            'ID_TEST' => $this->primaryKey()->autoIncrement(),
            'TEST_TEST' => $this->string()->comment('TEST COMMENT')
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('TEST_MIGRATION_CORE');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m180830_105152_test_migration cannot be reverted.\n";

        return false;
    }
    */
}
