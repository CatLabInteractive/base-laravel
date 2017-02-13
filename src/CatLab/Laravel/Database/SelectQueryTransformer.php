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
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Class SelectQueryTransformer
 * @package CatLab\Laravel\Database
 */
class SelectQueryTransformer
{
    /**
     * @param Builder $laravelQueryBuilder
     * @param SelectQueryParameters $filter
     */
    public static function toLaravel($laravelQueryBuilder, SelectQueryParameters $filter)
    {
        self::processWhereParameters($laravelQueryBuilder, $filter->getWhere());

        foreach ($filter->getSort() as $sort) {

            if (
                $laravelQueryBuilder instanceof Builder ||
                $laravelQueryBuilder instanceof Relation
            ) {
                $laravelQueryBuilder->orderBy(
                    self::translateParameter($laravelQueryBuilder, $sort->getColumn()),
                    $sort->getDirection()
                );
            } elseif ($laravelQueryBuilder instanceof Collection) {
                if ($sort->getDirection() == OrderParameter::DESC) {
                    $laravelQueryBuilder->sortByDesc($sort->getColumn());
                } else {
                    $laravelQueryBuilder->sortBy($sort->getColumn());
                }
            } else {
                throw new \InvalidArgumentException("Collection not supported");
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
    private static function processWhereParameters($query, $whereParameters)
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
    private static function processComparison($query, Comparison $comparison)
    {
        $subject = self::translateParameter($query, $comparison->getSubject());
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
     * @param Builder $laravelQueryBuilder
     * @param $parameter
     * @return mixed
     */
    private static function translateParameter($laravelQueryBuilder, $parameter)
    {
        if ($parameter instanceof Raw) {
            return \DB::raw($parameter->__toString());
        } else {
            return $parameter;
        }
    }
}