<?php


namespace yii\db\oci8;


class ColumnSchemaBuilder extends \yii\db\oci\ColumnSchemaBuilder
{
    /**
     * @var bool auto increment field
     */
    public $autoIncrement = false;

    /**
     * @var string table field comment
     */
    public $comment;

    /**
     * @return $this
     */
    public function autoIncrement()
    {
        $this->autoIncrement = true;
        return $this;
    }

    /**
     * @param string $comment
     * @return $this
     */
    public function comment($comment)
    {
        $this->comment = $comment;
        return $this;
    }
}