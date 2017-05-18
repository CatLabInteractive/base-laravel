<?php

namespace CatLab\Laravel\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UnexpectedEntity
 * @package CatLab\Laravel\Exceptions
 */
class UnexpectedEntity extends Exception
{
    /**
     * @param $entityClassName
     * @return UnexpectedEntity
     */
    public static function make($entityClassName)
    {
        return new self('Unexpected entity: '. $entityClassName . '. Entities should extend ' . Model::class);
    }
}