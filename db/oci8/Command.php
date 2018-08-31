<?php


namespace yii\db\oci8;


use Yii;
use yii\base\InvalidArgumentException;

class Command extends \yii\db\Command
{
    /**
     * @inheritdoc
     */
    public function createTable($table, $columns, $options = null){
        $result = null;
        foreach ($columns as $key => $column) {
            if (is_object($column) && $column->autoIncrement === true) {
                $this->db->createCommand(sprintf(
                    'CREATE SEQUENCE "SEQ_%s_ID" MINVALUE 1 START WITH 1 INCREMENT BY 1 NOCACHE',
                    $table
                ))->execute();
                $columns[$key]->comment = '_autoIncremented';
            }
            $result = $this->db->createCommand(sprintf(
                '
                        CREATE OR REPLACE TRIGGER "TRG_%s_ID"
                           BEFORE INSERT ON "%s"
                           FOR EACH ROW
                        BEGIN
                           IF INSERTING THEN
                              IF :NEW."%s" IS NULL THEN
                                 SELECT SEQ_%s_ID.NEXTVAL INTO :NEW."%s" FROM DUAL;
                              END IF;
                           END IF;
                        END;
                    ',
                $table, $table, $key, $table, $key
            ));
        }
        parent::createTable($table, $columns, $options)->execute();
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function dropTable($table){
        $tableSchema = $this->db->getTableSchema($table);
        if ($tableSchema === null) {
            throw new InvalidArgumentException("Unknown table: $table");
        }

        if (
            $tableSchema->sequenceName !== null &&
            $tableSchema->sequenceName == "SEQ_{$tableSchema->name}_ID"
        ) {
            $this->db->createCommand("DROP SEQUENCE \"SEQ_{$tableSchema->name}_ID\"")->execute();
        }
        return parent::dropTable($table);
    }
}