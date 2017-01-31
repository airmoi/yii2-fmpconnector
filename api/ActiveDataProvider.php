<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace airmoi\yii2fmconnector\api;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use airmoi\yii2fmconnector\api\Connection;
use yii\db\QueryInterface;
use yii\di\Instance;

/**
 * ActiveDataProvider implements a data provider based on [[airmoi\FileMaker\Command\Find]].
 *
 * ActiveDataProvider provides data by performing DB queries using [[airmoi\FileMaker\FileMaker]].
 *
 * The following is an example of using ActiveDataProvider to provide ActiveRecord instances:
 *
 * ~~~
 * $provider = new ActiveDataProvider([
 *     'query' => Post::find(),
 *     'pagination' => [
 *         'pageSize' => 20,
 *     ],
 * ]);
 *
 * // get the posts in the current page
 * $posts = $provider->getModels();
 * ~~~
 *
 * @author airmoi <airmoi@gmail.com>
 * @since 2.0
 */
class ActiveDataProvider extends \yii\data\BaseDataProvider
{
    /**
     * @var ActiveFind the query that is used to fetch data models and [[totalCount]]
     * if it is not explicitly set.
     */
    public $query;
    /**
     * @var string|callable the column that is used as the key of the data models.
     * This can be either a column name, or a callable that returns the key value of a given data model.
     *
     * If this is not set, the following rules will be used to determine the keys of the data models:
     *
     * - If [[query]] is an [[\yii\db\ActiveQuery]] instance, the primary keys of [[\yii\db\ActiveQuery::modelClass]] will be used.
     * - Otherwise, the keys of the [[models]] array will be used.
     *
     * @see getKeys()
     */
    public $key;
    /**
     * @var Connection the DB connection object or the application component ID of the DB connection.
     * If not set, the default DB connection will be used.
     */
    public $db;


    /**
     * Initializes the DB connection component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init()
    {
        parent::init();
        if (is_string($this->db)) {
            $this->db = Instance::ensure($this->db, Connection::className());
        }
    }

    /**
     * @inheritdoc
     */
    protected function prepareModels()
    {
        if (!$this->query instanceof ActiveFind) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements ActiveFind.');
        }
        
        if (($pagination = $this->getPagination()) !== false) {
            //Dirty hack : force page n° as totalcount is already returned by FileMaker PHP-API
            $pagination->setPage(Yii::$app->getRequest()->getQueryParam($pagination->pageParam, 1)-1, false);
            $this->query->limit($pagination->getLimit())->offset($pagination->getOffset());
        }
        if (($sort = $this->getSort()) !== false) {
            /*foreach($sort->getOrders() as $field => $order){
                $this->query->orderBy(); //append 'end' to asc/desc
            }*/
            $this->query->addOrderBy($sort->getOrders());
        }

        $models =  $this->query->all();
        if ($pagination instanceof \yii\data\Pagination) {
            $pagination->totalCount = $this->query->count();
        }
        return $models;
    }

    /**
     * @inheritdoc
     */
    protected function prepareKeys($models)
    {
        $keys = [];
        if ($this->key !== null) {
            foreach ($models as $model) {
                if (is_string($this->key)) {
                    $keys[] = $model[$this->key];
                } else {
                    $keys[] = call_user_func($this->key, $model);
                }
            }

            return $keys;
        } elseif ($this->query instanceof ActiveFind) {
            /* @var $class \yii\db\ActiveRecord */
            $class = $this->query->modelClass;
            $pks = $class::primaryKey();
            if (count($pks) === 1) {
                $pk = $pks[0];
                foreach ($models as $model) {
                    $keys[] = $model[$pk];
                }
            } else {
                foreach ($models as $model) {
                    $kk = [];
                    foreach ($pks as $pk) {
                        $kk[$pk] = $model[$pk];
                    }
                    $keys[] = $kk;
                }
            }

            return $keys;
        } else {
            return array_keys($models);
        }
    }

    /**
     * @inheritdoc
     */
    protected function prepareTotalCount()
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }
        ;
        return (int) $this->query->count('*', $this->db);
    }

    /**
     * @inheritdoc
     */
    public function setSort($value)
    {
        parent::setSort($value);
        if (($sort = $this->getSort()) !== false && $this->query instanceof ActiveFind) {
            /* @var $model Model */
            $model = new $this->query->modelClass;
            if (empty($sort->attributes)) {
                foreach ($model->attributes() as $attribute) {
                    $sort->attributes[$attribute] = [
                        'asc' => [$attribute => SORT_ASC],
                        'desc' => [$attribute => SORT_DESC],
                        'label' => $model->getAttributeLabel($attribute),
                    ];
                }
            } else {
                foreach ($sort->attributes as $attribute => $config) {
                    if (!isset($config['label'])) {
                        $sort->attributes[$attribute]['label'] = $model->getAttributeLabel($attribute);
                    }
                }
            }
        }
    }
}
