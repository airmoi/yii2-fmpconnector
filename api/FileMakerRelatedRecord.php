<?php
/**
 * @copyright 2016 Romain Dunand
 * @license MIT https://github.com/airmoi/yii2-fmpconnector/blob/master/LICENSE
 * @link https://github.com/airmoi/yii2-fmpconnector
 */

namespace airmoi\yii2fmconnector\api;

use yii;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use airmoi\FileMaker\FileMakerException;

/**
 * Class FileMakerRelatedRecord
 * @package airmoi\yii2fmconnector\api
 */
class FileMakerRelatedRecord extends FileMakerActiveRecord
{

    /**
     * Name of the relation
     * @var string
     */
    public $relationName;

    /**
     * Name of the FileMaker table occurrence the related record is based on
     * @return string
     */
    public $tableOccurence;

    /**
     * @return string
     * @throws NotSupportedException
     * @throws InvalidConfigException if the table for the AR class does not exist.
     */
    public function parentLayoutName()
    {
        return $this->parentRecord()->layoutName();
    }

    public function getTableSchemaFromParent($layout = null)
    {
        if ($this->parentRecord()->isPortal) {
            return $this->parentRecord()->getTableSchemaFromParent($layout)->relations[$this->relationName];
        } else {
            return $this->parentRecord()->getTableSchema($layout)->relations[$this->relationName];
        }
    }

    /**
     * Returns the list of all attribute names of the model.
     * The default implementation will return all column names of the table associated with this AR class.
     * @return array list of attribute names.
     */
    public function attributes()
    {
        $relationSchema = $this->getTableSchemaFromParent();
        $keys = array_keys($relationSchema->columns);
        return $keys;
    }


    /**
     * @param bool $runValidation
     * @param null $attributes
     * @return bool|int
     * @throws FileMakerException
     * @throws \Exception
     */
    public function insert($runValidation = true, $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            return false;
        }

        if (!$this->isPortal) {
            return $this->parentRecord()->insert($runValidation, $attributes);
        } else {
            $values = $this->getDirtyAttributes();
            foreach ($values as $field => $value) {
                $this->_record->setField($field, $value);
            }

            $token = 'insert ' . __CLASS__ . ' ' . $this->getRecId();
            Yii::beginProfile($token, 'yii\db\Command::query');
            try {
                $this->_record->commit();
                Yii::info($this->parentRecord()->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return 1;
            } catch (\Exception $e) {
                $this->addError('general', $e->getMessage());
                Yii::info($this->parentRecord()->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return false;
            }
        }
    }

    /**
     * @param bool $runValidation
     * @param null $attributeNames
     * @return bool|int
     * @throws \Exception
     * @throws FileMakerException
     */
    public function update($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }

        if (!$this->isPortal) {
            return $this->parentRecord()->update($runValidation, $attributeNames);
        } else {
            $values = $this->getDirtyAttributes();
            foreach ($values as $field => $value) {
                $this->_record->setField($field, $value);
            }

            $token = 'update ' . __CLASS__ . ' ' . $this->getRecId();
            Yii::beginProfile($token, 'yii\db\Command::query');
            try {
                $this->_record->commit();
                Yii::info($this->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return 1;
            } catch (\Exception $e) {
                $this->addError('general', $e->getMessage());
                Yii::info($this->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return false;
            }
        }
    }

    /**
     * Returns the attribute values that have been modified since they are loaded or saved most recently.
     * Prefix the relation tableName to field names
     * @param string[]|null $names the names of the attributes whose values may be returned if they are
     * changed recently. If null, [[attributes()]] will be used.
     * @return array the changed attribute values (name-value pairs)
     */
    public function getDirtyAttributes($names = null)
    {
        $values = parent::getDirtyAttributes($names);

        $prefixedValues = [];
        foreach ($values as $field => $value) {
            $prefixedValues[$this->tableOccurence . '::' . $field] = $value;
        }

        return $prefixedValues;
    }
}
