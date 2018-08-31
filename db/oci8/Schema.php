<?php


namespace yii\db\oci8;


use yii\db\oci\ColumnSchemaBuilder;

class Schema extends \yii\db\oci\Schema
{
    /**
     * @inheritdoc
     */
    public function createColumnSchemaBuilder($type, $length = null)
    {
        return new ColumnSchemaBuilder($type, $length);
    }

    /**
     * @inheritdoc
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this->db);
    }
}