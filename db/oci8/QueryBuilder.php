<?php


namespace yii\db\oci8;


use yii\base\InvalidArgumentException;
use yii\db\Connection;

class QueryBuilder extends \yii\db\oci\QueryBuilder
{
    /**
     * @inheritdoc
     */
    public function createSequence($name, $start = 1, $increment = 1, $max = 'NOMAXVALUE', $cache = 'NOCACHE'){
        return 'CREATE SEQUENCE '.$this->db->quoteTableName($name).' START WITH '.$this->db->quoteValue($start)
            . '  INCREMENT BY '.$this->db->quoteValue($increment). ' ' . (is_int($max) ? 'MAXVALUE '. $max : $max)
            . ' '. (is_int($cache) ? 'CACHE '. $cache : $cache);
    }

    /**
     * @inheritdoc
     */
    public function dropSequence($name){
        return "DROP SEQUENCE {$this->db->quoteTableName($name)}";
    }

    /**
     * @inheritdoc
     */
    public function checkIntegrity($check = true, $schema = '', $table = '')
    {
        if ($check) {
            $constraintStatus = 'DISABLED';
            $constraintCommand = 'enable';
        } else {
            $constraintStatus = 'ENABLED';
            $constraintCommand = 'disable';
        }

        return "
            BEGIN
              FOR c IN
              (SELECT c.owner, c.table_name, c.constraint_name
               FROM user_constraints c, user_tables t
               WHERE c.table_name = t.table_name
               AND c.status = '$constraintStatus'
               AND c.constraint_type = 'R'
               ORDER BY c.constraint_type)
              LOOP
                dbms_utility.exec_ddl_statement('alter table \"' || c.owner || '\".\"' || c.table_name || '\" $constraintCommand constraint ' || c.constraint_name);
              END LOOP;
            END;
        ";
    }
}