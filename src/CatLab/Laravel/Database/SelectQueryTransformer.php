<?php

namespace CatLab\Laravel\Database;

use CatLab\Base\Interfaces\Database\SelectQueryParameters;
use CatLab\Base\Interfaces\Database\WhereParameter;
use CatLab\Base\Interfaces\Grammar\AndConjunction;
use CatLab\Base\Interfaces\Grammar\OrConjunction;
use CatLab\Base\Interfaces\Parameters\Raw;
use Illuminate\Database\Query\Builder;

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
            $laravelQueryBuilder->orderBy(
                self::translateParameter($laravelQueryBuilder, $sort->getColumn()),
                $sort->getDirection()
            );
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
                $query->where(
                    self::translateParameter($query, $comparison->getSubject()),
                    $comparison->getOperator(),
                    self::translateParameter($query, $comparison->getValue())
                );
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
     * @param Builder $laravelQueryBuilder
     * @param $parameter
     * @return mixed
     */
    private static function translateParameter($laravelQueryBuilder, $parameter)
    {
        if ($parameter instanceof Raw) {
            return $laravelQueryBuilder->raw($parameter->__toString());
        } else {
            return $parameter;
        }
    }
}