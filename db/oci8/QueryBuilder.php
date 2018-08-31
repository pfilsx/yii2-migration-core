<?php


namespace yii\db\oci8;


use yii\base\InvalidArgumentException;
use yii\db\Connection;

class QueryBuilder extends \yii\db\oci\QueryBuilder
{
    /**
     * @inheritdoc
     */
    public function createSequence($table, $value = null)
    {
        $tableSchema = $this->db->getTableSchema($table);
        if ($tableSchema === null) {
            throw new InvalidArgumentException("Unknown table: $table");
        }
        if ($tableSchema->sequenceName === null) {
            return '';
        }

        if ($value !== null) {
            $value = (int) $value;
        } else {
            // use master connection to get the biggest PK value
            $value = $this->db->useMaster(function (Connection $db) use ($tableSchema) {
                    return $db->createCommand("SELECT MAX(\"{$tableSchema->primaryKey}\") FROM \"{$tableSchema->name}\"")->queryScalar();
                }) + 1;
        }

        return "CREATE SEQUENCE \"SEQ_{$tableSchema->name}_ID\" START WITH {$value} INCREMENT BY 1 NOMAXVALUE NOCACHE";
    }

    /**
     * @inheritdoc
     */
    public function dropSequence($table)
    {
        $tableSchema = $this->db->getTableSchema($table);
        if ($tableSchema === null) {
            throw new InvalidArgumentException("Unknown table: $table");
        }
        if ($tableSchema->sequenceName === null) {
            return '';
        }

        return "DROP SEQUENCE \"SEQ_{$tableSchema->name}_ID\"";
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