<?php

namespace CatLab\Laravel\Database;

use CatLab\Base\Enum\Operator;
use CatLab\Base\Interfaces\Database\OrderParameter;
use CatLab\Base\Interfaces\Database\SelectQueryParameters;
use CatLab\Base\Interfaces\Database\WhereParameter;
use CatLab\Base\Interfaces\Grammar\AndConjunction;
use CatLab\Base\Interfaces\Grammar\Comparison;
use CatLab\Base\Interfaces\Grammar\OrConjunction;
use CatLab\Base\Interfaces\Parameters\Raw;
use CatLab\Base\Models\Database\SortParameter;
use CatLab\Laravel\Exceptions\UnexpectedEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Class SelectQueryTransformer
 * @package CatLab\Laravel\Database
 */
class SelectQueryTransformer
{
    /**
     * @var string[]
     */
    protected $tableNames = [];

    /**
     * @param Builder $laravelQueryBuilder
     * @param SelectQueryParameters $filter
     */
    public function toLaravel($laravelQueryBuilder, SelectQueryParameters $filter)
    {
        self::processWhereParameters($laravelQueryBuilder, $filter->getWhere());

        foreach ($filter->getSort() as $sort) {
            if (
                $laravelQueryBuilder instanceof Builder ||
                $laravelQueryBuilder instanceof EloquentBuilder ||
                $laravelQueryBuilder instanceof Relation
            ) {
                $laravelQueryBuilder->orderBy(
                    $this->translateSortColumn($laravelQueryBuilder, $sort),
                    $sort->getDirection()
                );
            } elseif ($laravelQueryBuilder instanceof Collection) {
                if ($sort->getDirection() == OrderParameter::DESC) {
                    $laravelQueryBuilder->sortByDesc($this->translateSortColumn($laravelQueryBuilder, $sort));
                } else {
                    $laravelQueryBuilder->sortBy($this->translateSortColumn($laravelQueryBuilder, $sort));
                }
            } else {
                throw new \InvalidArgumentException("Collection not supported: " . get_class($laravelQueryBuilder));
            }
        }

        if ($filter->getLimit()) {
            if ($filter->getLimit()->getAmount()) {
                $laravelQueryBuilder->take($filter->getLimit()->getAmount());
            }

            if ($filter->getLimit()->getOffset()) {
                $laravelQueryBuilder->skip($filter->getLimit()->getOffset());
            }
        }
    }

    /**
     * @param Builder $query
     * @param WhereParameter[] $whereParameters
     */
    protected function processWhereParameters($query, $whereParameters)
    {
        foreach ($whereParameters as $where) {
            /** @var Builder $query */
            if ($comparison = $where->getComparison()) {
                self::processComparison($query, $comparison);
            }

            foreach ($where->getChildren() as $child) {
                if ($child instanceof AndConjunction) {
                    $query->where(function ($query) use ($child) {
                        self::processWhereParameters($query, [$child->getSubject()]);
                    });
                } elseif ($child instanceof OrConjunction) {
                    $query->orWhere(function ($query) use ($child) {
                        self::processWhereParameters($query, [$child->getSubject()]);
                    });
                } else {
                    throw new \InvalidArgumentException("Got an unknown conjunction");
                }
            }
        }
    }

    /**
     * Process a single comparison
     * @param $query
     * @param Comparison $comparison
     */
    protected function processComparison($query, Comparison $comparison)
    {
        $subject = self::translateColumnName($query, $comparison);
        $value = self::translateParameter($query, $comparison->getValue());
        $operator = $comparison->getOperator();

        switch ($operator) {
            case Operator::SEARCH:
                $value = '%' . $value . '%';
                $operator = 'LIKE';
                break;
        }

        $query->where($subject, $operator, $value);
    }

    /**
     * @param $query
     * @param Comparison $column
     * @return string
     */
    protected function translateColumnName($query, Comparison $column)
    {
        // Get subject contains the column name
        $subject = $column->getSubject();
        if ($subject instanceof Raw) {
            return \DB::raw($subject->__toString());
        }

        $entity = $column->getEntity();
        if ($entity) {
            $table = $this->resolveEntityTable($entity);
            if ($table) {
                $subject = $table . '.' . $subject;
            }
        }

        return $subject;
    }

    /**
     * @param $query
     * @param OrderParameter $parameter
     * @return string
     */
    protected function translateSortColumn($query, OrderParameter $parameter)
    {
        // Get subject contains the column name
        $subject = $parameter->getColumn();
        if ($subject instanceof Raw) {
            return \DB::raw($subject->__toString());
        }

        $entity = $parameter->getEntity();
        if ($entity) {
            $table = $this->resolveEntityTable($entity);
            if ($table) {
                $subject = $table . '.' . $subject;
            }
        }

        return $subject;
    }

    /**
     * @param Builder $laravelQueryBuilder
     * @param $parameter
     * @return mixed
     */
    protected function translateParameter($laravelQueryBuilder, $parameter)
    {
        if ($parameter instanceof Raw) {
            return \DB::raw($parameter->__toString());
        } else {
            return $parameter;
        }
    }

    /**
     * Translate a parameter subject and translate it to the table name
     * @param $subject
     * @return string
     */
    protected function getEntityTable($subject)
    {
        if (!isset($this->tableNames[$subject])) {
            $this->tableNames[$subject] = $this->resolveEntityTable($subject);
        }

        return $this->tableNames[$subject];
    }

    /**
     * @param $subject
     * @return string
     * @throws UnexpectedEntity
     */
    protected function resolveEntityTable($subject)
    {
        if (class_exists($subject)) {
            $model = new $subject;
            if ($model instanceof Model) {
                return $model->getTable();
            } else {
                throw UnexpectedEntity::make($subject);
            }

        } else {
            return $subject;
        }

    }
}